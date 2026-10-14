@extends('layouts.app')

@section('title', 'Découpage automatique')

@section('content')
    <section class="panel">
        <h1>Découpage automatique des tickets</h1>
        <p class="lead">
            Repère chaque ticket via son numéro <strong>[1]</strong>, <strong>[2]</strong>…
            puis découpe la bonne case.
        </p>

        <div class="visual-controls">
            <button type="button" class="btn btn-ghost" id="btn-detect">Vérifier les numéros</button>
            <button type="button" class="btn btn-primary" id="btn-repair" disabled>Réparer les manquants</button>
            <button type="button" class="btn btn-primary btn-lg" id="btn-auto" disabled>Découper automatiquement</button>
        </div>

        <details class="manual-grid" style="margin-top:1rem;">
            <summary style="cursor:pointer;color:var(--muted);">Option manuelle (grille)</summary>
            <div class="visual-controls" style="margin-top:0.75rem;">
                <label>Colonnes <input type="number" id="cols" min="1" max="8" value="3"></label>
                <label>Lignes <input type="number" id="rows" min="1" max="20" value="5"></label>
                <label>Marge (%) <input type="number" id="margin" min="0" max="10" step="0.5" value="1"></label>
                <button type="button" class="btn btn-ghost" id="btn-cut-grid">Découper en grille</button>
            </div>
        </details>

        <p id="status" class="lead" style="margin-top:1rem;">Préparation…</p>
        <div id="sequence-report" class="alert alert-info" style="display:none;"></div>
        <div id="missing-box" class="alert alert-error" style="display:none;margin-top:1rem;">
            <strong>Numéros manquants :</strong> <span id="missing-list"></span>
            <div style="margin-top:.75rem;">
                <button type="button" class="btn btn-primary" id="btn-repair-2">Réparer et ajouter</button>
            </div>
        </div>

        <div id="preview" class="visual-preview"></div>

        <form id="cut-form" action="{{ route('tickets.visual.store', $batch) }}" method="POST" style="display:none;">
            @csrf
            <input type="hidden" name="allow_gaps" id="allow_gaps" value="0">
            <div id="ticket-fields"></div>
        </form>

        <div class="actions">
            <a class="btn btn-ghost" href="{{ route('tickets.index') }}">Retour</a>
            <form action="{{ route('tickets.destroy', $batch) }}" method="POST" onsubmit="return confirm('Annuler et supprimer ce lot ?');">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-danger">Annuler</button>
            </form>
        </div>
    </section>
@endsection

@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js" crossorigin="anonymous"></script>
<script>
(function () {
    var statusEl = document.getElementById('status');
    var reportEl = document.getElementById('sequence-report');
    var missingBox = document.getElementById('missing-box');
    var missingListEl = document.getElementById('missing-list');
    var previewEl = document.getElementById('preview');
    var form = document.getElementById('cut-form');
    var fields = document.getElementById('ticket-fields');
    var allowGapsEl = document.getElementById('allow_gaps');
    var btnDetect = document.getElementById('btn-detect');
    var btnRepair = document.getElementById('btn-repair');
    var btnRepair2 = document.getElementById('btn-repair-2');
    var btnAuto = document.getElementById('btn-auto');
    var btnGrid = document.getElementById('btn-cut-grid');
    var pdfUrl = @json(route('tickets.pdf', $batch));

    var pdfDoc = null;
    var busy = false;
    var pages = [];
    var ticketMap = new Map();

    function setStatus(m) { statusEl.textContent = m; }
    function yieldUi() { return new Promise(function (r) { setTimeout(r, 15); }); }

    function setBusy(on, label) {
        busy = on;
        [btnDetect, btnRepair, btnRepair2, btnAuto, btnGrid].forEach(function (b) {
            if (b && on) b.disabled = true;
        });
        if (!on) {
            btnDetect.disabled = false;
            btnGrid.disabled = false;
            btnRepair.disabled = ticketMap.size === 0;
            btnRepair2.disabled = ticketMap.size === 0;
            btnAuto.disabled = ticketMap.size === 0;
        }
        if (label) setStatus(label);
    }

    function showErr(e) {
        console.error(e);
        setStatus('Erreur : ' + (e && e.message ? e.message : e));
        setBusy(false);
    }

    btnDetect.addEventListener('click', function () {
        setStatus('Vérification…');
        runDetect().catch(showErr);
    });
    btnRepair.addEventListener('click', function () { runRepair().catch(showErr); });
    btnRepair2.addEventListener('click', function () { runRepair().catch(showErr); });
    btnAuto.addEventListener('click', function () { runCut().catch(showErr); });
    btnGrid.addEventListener('click', function () { runGrid().catch(showErr); });

    if (typeof pdfjsLib === 'undefined') {
        setStatus('PDF.js non chargé. Rechargez la page (Ctrl+F5).');
        return;
    }
    pdfjsLib.GlobalWorkerOptions.workerSrc =
        'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';

    /** Uniquement [n] ou (n) */
    function parseNum(text) {
        if (!text) return null;
        var c = String(text).replace(/\s+/g, '');
        var m = c.match(/^[\[(](\d{1,4})[\])]$/);
        if (m) return parseInt(m[1], 10);
        m = c.match(/[\[(](\d{1,4})[\])]/);
        return m ? parseInt(m[1], 10) : null;
    }

    function median(arr) {
        if (!arr.length) return 0;
        var a = arr.slice().sort(function (x, y) { return x - y; });
        return a[Math.floor(a.length / 2)];
    }

    async function loadPieces(pageInfo) {
        if (pageInfo.pieces) return pageInfo.pieces;
        var content = await pageInfo.page.getTextContent();
        var pieces = [];
        for (var i = 0; i < content.items.length; i++) {
            var item = content.items[i];
            var raw = item.str || '';
            if (!String(raw).trim()) continue;
            var pt = pageInfo.viewport.convertToViewportPoint(item.transform[4], item.transform[5]);
            pieces.push({ text: raw, x: pt[0], y: pt[1] });
        }
        pageInfo.pieces = pieces;
        return pieces;
    }

    /**
     * Extrait TOUS les marqueurs [n] / (n) de la page.
     */
    async function extractMarkers(pageInfo) {
        var pieces = await loadPieces(pageInfo);
        var byNum = {};

        function push(num, x, y) {
            if (num == null || num < 1 || num > 9999) return;
            if (!byNum[num] || x > byNum[num].x) {
                byNum[num] = { num: num, x: x, y: y, pageInfo: pageInfo };
            }
        }

        // Item seul
        for (var i = 0; i < pieces.length; i++) {
            push(parseNum(pieces[i].text.trim()), pieces[i].x, pieces[i].y);
        }

        // Fragments voisins : "[" + "29" + "]"
        for (var i = 0; i < pieces.length; i++) {
            var chunk = pieces[i].text;
            var cy = pieces[i].y;
            var lx = pieces[i].x;
            for (var j = i + 1; j < Math.min(i + 6, pieces.length); j++) {
                if (Math.abs(pieces[j].y - cy) > 18) break;
                if (pieces[j].x + 3 < lx) break;
                if (pieces[j].x - lx > 120) break;
                chunk += pieces[j].text;
                lx = pieces[j].x;
                var n = parseNum(chunk);
                if (n != null) {
                    push(n, pieces[j].x, cy);
                    break;
                }
            }
        }

        // Regex globale + position du bon fragment (pas includes "1" dans l'IP)
        var full = pieces.map(function (p) { return p.text; }).join('');
        var re = /[\[(]\s*(\d{1,4})\s*[\])]/g;
        var m;
        while ((m = re.exec(full)) !== null) {
            var n = parseInt(m[1], 10);
            var token = m[0].replace(/\s+/g, '');
            var hit = null;
            for (var k = 0; k < pieces.length; k++) {
                var t = String(pieces[k].text).replace(/\s+/g, '');
                if (t === token || t === '[' + n + ']' || t === '(' + n + ')' || t === String(n)) {
                    // préfère un fragment près d'une parenthèse
                    hit = pieces[k];
                    if (/[\[\()]/.test(pieces[k].text) || /^\d+$/.test(t)) break;
                }
            }
            // Si chiffre seul, chercher "[" juste avant
            if (hit && /^\d+$/.test(String(hit.text).trim())) {
                for (var k = 0; k < pieces.length; k++) {
                    if (Math.abs(pieces[k].y - hit.y) < 12 && pieces[k].x < hit.x && hit.x - pieces[k].x < 40) {
                        if (/[\[(]/.test(pieces[k].text)) {
                            hit = { text: hit.text, x: hit.x, y: hit.y };
                            break;
                        }
                    }
                }
            }
            if (hit) push(n, hit.x, hit.y);
        }

        return Object.keys(byNum).map(function (k) { return byNum[k]; });
    }

    /**
     * Calcule largeur/hauteur d'une case à partir des distances entre marqueurs.
     */
    function estimateCellSize(markers, pageW, pageH) {
        var dx = [], dy = [];
        for (var i = 0; i < markers.length; i++) {
            var bestX = Infinity, bestY = Infinity;
            for (var j = 0; j < markers.length; j++) {
                if (i === j) continue;
                var ax = markers[j].x - markers[i].x;
                var ay = markers[j].y - markers[i].y;
                if (ax > 30 && Math.abs(ay) < 40) bestX = Math.min(bestX, ax);
                if (ay > 30 && Math.abs(ax) < 80) bestY = Math.min(bestY, ay);
            }
            if (bestX < Infinity) dx.push(bestX);
            if (bestY < Infinity) dy.push(bestY);
        }

        var cellW = median(dx);
        var cellH = median(dy);

        // Fallbacks raisonnables (grille MikroTik)
        if (cellW < 60) cellW = pageW / 3;
        if (cellH < 40) cellH = pageH / 5;
        // Limites
        cellW = Math.max(100, Math.min(pageW * 0.55, cellW));
        cellH = Math.max(70, Math.min(pageH * 0.45, cellH));

        return { w: cellW, h: cellH };
    }

    /**
     * Case autour du numéro [n] (en haut à droite du ticket).
     */
    function rectFromMarker(m, cell) {
        var rightPad = Math.max(6, cell.w * 0.04);
        var topPad = Math.max(8, cell.h * 0.12);
        var x = Math.round(m.x - cell.w + rightPad);
        var y = Math.round(m.y - topPad);
        var page = m.pageInfo;
        var maxW = page.canvas.width;
        var maxH = page.canvas.height;
        if (x < 0) x = 0;
        if (y < 0) y = 0;
        var w = Math.round(cell.w);
        var h = Math.round(cell.h);
        if (x + w > maxW) w = maxW - x;
        if (y + h > maxH) h = maxH - y;
        return {
            x: x, y: y, w: w, h: h,
            num: m.num,
            pageNum: page.pageNum,
            canvas: page.canvas,
            from: 'marker'
        };
    }

    function findMissing() {
        var nums = Array.from(ticketMap.keys()).sort(function (a, b) { return a - b; });
        if (!nums.length) return [];
        var max = nums[nums.length - 1];
        // Si très peu de tickets vs max, ne pas inventer une énorme liste :
        // on considère le max = plus grand numéro TROUVÉ seulement si densité OK
        var density = nums.length / max;
        if (density < 0.35 && nums.length < 40) {
            // Peu de marqueurs : manquants = pas de faux trou 1..max
            // On signale plutôt les trous locaux dans les numéros trouvés
            var missing = [];
            for (var i = 1; i < nums.length; i++) {
                for (var n = nums[i - 1] + 1; n < nums[i]; n++) missing.push(n);
            }
            // Aussi 1 .. first-1 si first > 1
            for (var n = 1; n < nums[0]; n++) missing.push(n);
            return missing;
        }
        var missing = [];
        for (var n = 1; n <= max; n++) {
            if (!ticketMap.has(n)) missing.push(n);
        }
        return missing;
    }

    function drawAll() {
        var byPage = {};
        ticketMap.forEach(function (t) {
            (byPage[t.pageNum] || (byPage[t.pageNum] = [])).push(t);
        });
        pages.forEach(function (p) {
            var ctx = p.overlay.getContext('2d');
            ctx.clearRect(0, 0, p.overlay.width, p.overlay.height);
            ctx.lineWidth = 2;
            ctx.font = 'bold 14px Arial';
            (byPage[p.pageNum] || []).forEach(function (r) {
                ctx.strokeStyle = 'rgba(61,214,140,.95)';
                ctx.strokeRect(r.x, r.y, r.w, r.h);
                ctx.fillStyle = ctx.strokeStyle;
                ctx.fillText(String(r.num), r.x + 4, r.y + 16);
            });
        });
    }

    function refreshReport() {
        var missing = findMissing();
        drawAll();
        reportEl.style.display = 'block';
        btnRepair.disabled = false;
        btnRepair2.disabled = false;
        btnAuto.disabled = ticketMap.size === 0;

        if (!ticketMap.size) {
            reportEl.className = 'alert alert-error';
            reportEl.textContent = 'Aucun numéro [n] trouvé dans le PDF.';
            missingBox.style.display = 'none';
            return;
        }

        var nums = Array.from(ticketMap.keys()).sort(function (a, b) { return a - b; });
        var max = nums[nums.length - 1];

        if (!missing.length) {
            reportEl.className = 'alert alert-success';
            reportEl.textContent = 'OK : ' + ticketMap.size + ' ticket(s) · n°' + nums[0] + '→' + max + ' sans trou.';
            missingBox.style.display = 'none';
        } else {
            reportEl.className = 'alert alert-error';
            reportEl.textContent = ticketMap.size + ' trouvé(s) · ' + missing.length + ' manquant(s). Cliquez Réparer.';
            missingBox.style.display = 'block';
            missingListEl.textContent = missing.length > 60
                ? missing.slice(0, 60).join(', ') + ' … (+' + (missing.length - 60) + ')'
                : missing.join(', ');
        }
    }

    async function detectAllTickets() {
        ticketMap = new Map();

        for (var p = 0; p < pages.length; p++) {
            var pageInfo = pages[p];
            setStatus('Lecture numéros page ' + pageInfo.pageNum + '/' + pages.length + '…');
            await yieldUi();

            var markers = await extractMarkers(pageInfo);
            if (!markers.length) continue;

            var cell = estimateCellSize(markers, pageInfo.canvas.width, pageInfo.canvas.height);

            for (var i = 0; i < markers.length; i++) {
                var m = markers[i];
                var rect = rectFromMarker(m, cell);
                // Évite les chevauchements forts : garde la 1re / meilleure
                var clash = false;
                ticketMap.forEach(function (t) {
                    if (t.pageNum !== pageInfo.pageNum) return;
                    var x1 = Math.max(t.x, rect.x), y1 = Math.max(t.y, rect.y);
                    var x2 = Math.min(t.x + t.w, rect.x + rect.w), y2 = Math.min(t.y + t.h, rect.y + rect.h);
                    var inter = Math.max(0, x2 - x1) * Math.max(0, y2 - y1);
                    var union = t.w * t.h + rect.w * rect.h - inter;
                    if (union && inter / union > 0.55) clash = true;
                });
                if (clash && ticketMap.has(m.num)) continue;
                ticketMap.set(m.num, rect);
            }
        }
    }

    async function runDetect() {
        if (busy) return;
        setBusy(true, 'Vérification des numéros…');
        await yieldUi();
        if (!pdfDoc) await initPdf();
        if (!pages.length) await renderPages();
        await detectAllTickets();
        refreshReport();
        setBusy(false, ticketMap.size ? (ticketMap.size + ' ticket(s) détecté(s).') : 'Aucun numéro.');
    }

    async function runRepair() {
        if (busy) return;
        setBusy(true, 'Réparation…');
        await yieldUi();

        // Relance une détection complète (souvent suffisant)
        await detectAllTickets();

        var missing = findMissing();
        if (!missing.length) {
            refreshReport();
            setBusy(false, 'Séquence complète.');
            return;
        }

        // Interpoler les positions manquantes entre voisins connus
        var recovered = 0;
        for (var mi = 0; mi < missing.length; mi++) {
            var num = missing[mi];
            if (ticketMap.has(num)) continue;

            var before = null, after = null;
            for (var b = num - 1; b >= 1; b--) {
                if (ticketMap.has(b)) { before = ticketMap.get(b); break; }
            }
            for (var a = num + 1; a <= num + 50; a++) {
                if (ticketMap.has(a)) { after = ticketMap.get(a); break; }
            }

            var pageInfo = null;
            var x, y, w, h;
            if (before && after && before.pageNum === after.pageNum) {
                var steps = after.num - before.num;
                var t = (num - before.num) / steps;
                x = before.x + (after.x - before.x) * t;
                y = before.y + (after.y - before.y) * t;
                w = (before.w + after.w) / 2;
                h = (before.h + after.h) / 2;
                pageInfo = pages.find(function (p) { return p.pageNum === before.pageNum; });
            } else if (before) {
                // Même ligne : avance d'une largeur
                w = before.w; h = before.h;
                x = before.x + w;
                y = before.y;
                pageInfo = pages.find(function (p) { return p.pageNum === before.pageNum; });
                if (pageInfo && x + w > pageInfo.canvas.width) {
                    x = 2;
                    y = before.y + h;
                }
            } else if (after) {
                w = after.w; h = after.h;
                x = after.x - w;
                y = after.y;
                pageInfo = pages.find(function (p) { return p.pageNum === after.pageNum; });
                if (x < 0) x = 0;
            }

            if (!pageInfo || w < 40 || h < 30) continue;

            // Vérifie qu'un marqueur [num] existe bien près de cette zone (texte)
            var markers = await extractMarkers(pageInfo);
            var hit = markers.find(function (m) { return m.num === num; });
            if (hit) {
                var cell = { w: w, h: h };
                ticketMap.set(num, rectFromMarker(hit, cell));
                recovered++;
            } else if (before || after) {
                // Accepte l'interpolation seulement si voisins proches
                var near = (before && num - before.num <= 3) || (after && after.num - num <= 3);
                if (near) {
                    ticketMap.set(num, {
                        x: Math.round(x), y: Math.round(y),
                        w: Math.round(w), h: Math.round(h),
                        num: num, pageNum: pageInfo.pageNum,
                        canvas: pageInfo.canvas, from: 'repair'
                    });
                    recovered++;
                }
            }
        }

        // Dernière passe : re-extraire tous les marqueurs (au cas où)
        await detectAllTickets();
        // Remettre les réparations non écrasées : detect remplit déjà tout

        refreshReport();
        setBusy(false, 'Réparation terminée · ' + ticketMap.size + ' ticket(s) · ' + findMissing().length + ' manquant(s).');
    }

    function crop(canvas, rect) {
        var tile = document.createElement('canvas');
        tile.width = Math.max(1, Math.floor(rect.w));
        tile.height = Math.max(1, Math.floor(rect.h));
        var ctx = tile.getContext('2d');
        ctx.fillStyle = '#fff';
        ctx.fillRect(0, 0, tile.width, tile.height);
        ctx.drawImage(canvas, Math.floor(rect.x), Math.floor(rect.y), tile.width, tile.height, 0, 0, tile.width, tile.height);
        return tile;
    }

    function submitTiles(tiles, allowGaps) {
        allowGapsEl.value = allowGaps ? '1' : '0';
        fields.innerHTML = '';
        tiles.forEach(function (tile) {
            var a = document.createElement('input');
            a.type = 'hidden'; a.name = 'tickets[]'; a.value = tile.toDataURL('image/png');
            fields.appendChild(a);
            var b = document.createElement('input');
            b.type = 'hidden'; b.name = 'ticket_nos[]';
            b.value = tile._num != null ? String(tile._num) : '';
            fields.appendChild(b);
        });
        form.submit();
    }

    async function runCut() {
        if (busy) return;
        if (!ticketMap.size) {
            setStatus('Vérifiez d’abord les numéros.');
            return;
        }
        setBusy(true, 'Découpage…');
        await yieldUi();

        var missing = findMissing();
        if (missing.length) {
            var go = confirm(missing.length + ' manquant(s).\nOK = réparer puis découper\nAnnuler = enregistrer les ' + ticketMap.size + ' trouvés');
            if (go) {
                setBusy(false);
                await runRepair();
                setBusy(true, 'Découpage…');
            }
        }

        missing = findMissing();
        var ordered = Array.from(ticketMap.entries()).sort(function (a, b) { return a[0] - b[0]; });
        var tiles = ordered.map(function (e) {
            var t = e[1];
            var tile = crop(t.canvas, t);
            tile._num = t.num;
            return tile;
        });

        if (!tiles.length) {
            setBusy(false, 'Rien à envoyer.');
            return;
        }

        if (missing.length && !confirm('Encore ' + missing.length + ' manquant(s). Enregistrer ' + tiles.length + ' tickets ?')) {
            setBusy(false, 'Annulé.');
            return;
        }

        setStatus('Envoi de ' + tiles.length + ' ticket(s)…');
        submitTiles(tiles, missing.length > 0);
    }

    async function runGrid() {
        if (busy) return;
        setBusy(true, 'Grille…');
        if (!pages.length) await renderPages();

        var cols = Math.max(1, parseInt(document.getElementById('cols').value, 10) || 3);
        var rows = Math.max(1, parseInt(document.getElementById('rows').value, 10) || 5);
        var marginPct = Math.max(0, parseFloat(document.getElementById('margin').value) || 0) / 100;
        ticketMap = new Map();
        var n = 1;

        pages.forEach(function (pageInfo) {
            var canvas = pageInfo.canvas;
            var mx = canvas.width * marginPct;
            var my = canvas.height * marginPct;
            var cellW = Math.floor((canvas.width - mx * 2) / cols);
            var cellH = Math.floor((canvas.height - my * 2) / rows);
            for (var r = 0; r < rows; r++) {
                for (var c = 0; c < cols; c++) {
                    ticketMap.set(n, {
                        x: Math.floor(mx + c * cellW),
                        y: Math.floor(my + r * cellH),
                        w: cellW, h: cellH,
                        num: n, pageNum: pageInfo.pageNum,
                        canvas: canvas, from: 'grid'
                    });
                    n++;
                }
            }
        });

        // Retire cases quasi vides
        Array.from(ticketMap.entries()).forEach(function (e) {
            var tile = crop(e[1].canvas, e[1]);
            var data = tile.getContext('2d').getImageData(0, 0, tile.width, tile.height).data;
            var dark = 0, sample = 0;
            for (var i = 0; i < data.length; i += 48 * 4) {
                sample++;
                if ((data[i] + data[i + 1] + data[i + 2]) / 3 < 230) dark++;
            }
            if (!sample || dark / sample < 0.015) ticketMap.delete(e[0]);
        });

        var vals = Array.from(ticketMap.values());
        ticketMap = new Map();
        vals.forEach(function (t, i) {
            t.num = i + 1;
            ticketMap.set(i + 1, t);
        });
        refreshReport();
        var tiles = vals.map(function (t) {
            var tile = crop(t.canvas, t);
            tile._num = t.num;
            return tile;
        });
        setStatus('Envoi grille (' + tiles.length + ')…');
        submitTiles(tiles, false);
    }

    async function renderPages() {
        previewEl.innerHTML = '';
        pages.length = 0;
        var scale = 1.8;
        for (var pageNum = 1; pageNum <= pdfDoc.numPages; pageNum++) {
            setStatus('Rendu page ' + pageNum + '/' + pdfDoc.numPages + '…');
            await yieldUi();
            var page = await pdfDoc.getPage(pageNum);
            var viewport = page.getViewport({ scale: scale });
            var wrap = document.createElement('div');
            wrap.className = 'page-wrap';
            var canvas = document.createElement('canvas');
            canvas.width = viewport.width;
            canvas.height = viewport.height;
            await page.render({
                canvasContext: canvas.getContext('2d', { willReadFrequently: true }),
                viewport: viewport
            }).promise;
            var overlay = document.createElement('canvas');
            overlay.className = 'grid-overlay';
            overlay.width = canvas.width;
            overlay.height = canvas.height;
            wrap.appendChild(canvas);
            wrap.appendChild(overlay);
            previewEl.appendChild(wrap);
            pages.push({ canvas: canvas, overlay: overlay, page: page, viewport: viewport, pageNum: pageNum, pieces: null });
        }
    }

    async function initPdf() {
        setStatus('Chargement du PDF…');
        pdfDoc = await pdfjsLib.getDocument({ url: pdfUrl, withCredentials: true }).promise;
        setStatus(pdfDoc.numPages + ' page(s) prêtes. Cliquez sur « Vérifier les numéros ».');
        btnDetect.disabled = false;
        btnGrid.disabled = false;
    }

    initPdf().catch(function (e) {
        console.error(e);
        setStatus('Impossible de charger le PDF : ' + (e && e.message ? e.message : e));
    });
})();
</script>
@endpush

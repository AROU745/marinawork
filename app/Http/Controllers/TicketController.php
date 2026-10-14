<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\TicketBatch;
use App\Services\PdfEmbeddedImageExtractor;
use App\Services\PdfTicketExtractor;
use App\Services\TicketGridSplitter;
use App\Services\TicketImageGenerator;
use App\Support\TicketDurations;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TicketController extends Controller
{
    public function index(): View
    {
        $batches = TicketBatch::query()
            ->latest()
            ->get();

        $batchesByDuration = collect(TicketDurations::all())->mapWithKeys(
            fn (string $label, string $key) => [
                $key => [
                    'label' => $label,
                    'batches' => $batches->where('duration_key', $key)->values(),
                    'remaining' => $batches->where('duration_key', $key)->sum(fn (TicketBatch $b) => $b->remainingCount()),
                    'total' => $batches->where('duration_key', $key)->sum('total_tickets'),
                ],
            ]
        );

        $unclassified = $batches->filter(fn (TicketBatch $b) => ! TicketDurations::isValid($b->duration_key))->values();

        return view('tickets.index', [
            'batches' => $batches,
            'batchesByDuration' => $batchesByDuration,
            'unclassified' => $unclassified,
            'durations' => TicketDurations::all(),
        ]);
    }

    public function upload(
        Request $request,
        PdfTicketExtractor $extractor,
        TicketImageGenerator $images,
        PdfEmbeddedImageExtractor $embeddedImages,
        TicketGridSplitter $gridSplitter,
    ): RedirectResponse {
        $request->validate([
            'pdf' => ['required', 'file', 'mimes:pdf', 'max:20480'],
            'duration_key' => ['required', TicketDurations::validationRule()],
            'cols' => ['nullable', 'integer', 'min:1', 'max:6'],
            'rows' => ['nullable', 'integer', 'min:0', 'max:20'],
        ], [
            'pdf.required' => 'Veuillez choisir un fichier PDF.',
            'pdf.mimes' => 'Le fichier doit être un PDF.',
            'pdf.max' => 'Le PDF ne doit pas dépasser 20 Mo.',
            'duration_key.required' => 'Choisissez la durée du lot (15 min, 1 h…).',
            'duration_key.in' => 'Durée de lot invalide.',
        ]);

        $durationKey = $request->string('duration_key')->toString();
        $file = $request->file('pdf');
        $path = $file->store('pdfs', 'local');
        $absolutePath = Storage::disk('local')->path($path);
        $cols = (int) ($request->input('cols') ?: 2);
        $rows = (int) ($request->input('rows') ?: 0);

        try {
            $extracted = $extractor->extract($absolutePath);
        } catch (\Throwable $e) {
            $extracted = [];
        }

        if (count($extracted) > 0) {
            $batch = $this->storeTextTickets($file->getClientOriginalName(), $path, $durationKey, $extracted, $images);

            return redirect()
                ->route('tickets.index')
                ->with('success', count($extracted).' ticket(s) '.$batch->durationLabel().' scanné(s).');
        }

        $jpegs = $embeddedImages->extractJpegs($absolutePath);
        $imageTickets = $this->imagesToTicketBinaries($jpegs, $gridSplitter, $cols, $rows);

        if (count($imageTickets) > 0) {
            $batch = $this->storeImageTickets($file->getClientOriginalName(), $path, $durationKey, $imageTickets, $images);

            return redirect()
                ->route('tickets.index')
                ->with('success', count($imageTickets).' ticket(s) '.$batch->durationLabel().' découpé(s).');
        }

        $batch = TicketBatch::create([
            'original_filename' => $file->getClientOriginalName(),
            'duration_key' => $durationKey,
            'stored_path' => $path,
            'total_tickets' => 0,
            'current_index' => 0,
            'needs_visual_scan' => true,
        ]);

        return redirect()
            ->route('tickets.visual', $batch)
            ->with('info', 'Lot '.$batch->durationLabel().' : découpez les tickets visuellement.');
    }

    public function visual(TicketBatch $batch): View|RedirectResponse
    {
        if (! $batch->needs_visual_scan && $batch->total_tickets > 0) {
            return redirect()->route('tickets.show', $batch);
        }

        return view('tickets.visual', [
            'batch' => $batch,
        ]);
    }

    public function pdfFile(TicketBatch $batch): StreamedResponse|Response
    {
        if (! $batch->stored_path || ! Storage::disk('local')->exists($batch->stored_path)) {
            abort(404);
        }

        return Storage::disk('local')->response($batch->stored_path, $batch->original_filename, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$batch->original_filename.'"',
            'Cache-Control' => 'private, max-age=0, no-cache',
        ]);
    }

    public function storeVisual(Request $request, TicketBatch $batch, TicketImageGenerator $images): RedirectResponse
    {
        $request->validate([
            'tickets' => ['required', 'array', 'min:1'],
            'tickets.*' => ['required', 'string'],
            'ticket_nos' => ['nullable', 'array'],
            'ticket_nos.*' => ['nullable', 'string'],
        ], [
            'tickets.required' => 'Aucun ticket découpé. Relancez le découpage automatique.',
        ]);

        $binaries = [];
        $numbers = $request->input('ticket_nos', []);

        foreach ($request->input('tickets') as $index => $dataUrl) {
            $binary = $this->dataUrlToBinary((string) $dataUrl);
            if ($binary === null) {
                continue;
            }

            $rawNo = $numbers[$index] ?? null;
            $ticketNo = is_numeric($rawNo) ? (int) $rawNo : null;

            $binaries[] = [
                'binary' => $binary,
                'ticket_no' => $ticketNo,
            ];
        }

        if (count($binaries) === 0) {
            return back()->withErrors(['tickets' => 'Impossible de lire les images découpées.']);
        }

        // Un seul ticket par numéro, tri croissant → aucun doublon / désordre
        $byNumber = [];
        $withoutNumber = [];
        foreach ($binaries as $item) {
            if ($item['ticket_no'] !== null) {
                $byNumber[$item['ticket_no']] = $item;
            } else {
                $withoutNumber[] = $item;
            }
        }

        ksort($byNumber, SORT_NUMERIC);
        $ordered = array_values($byNumber);

        // Complète les images sans numéro à la fin (grille manuelle déjà numérotée en principe)
        foreach ($withoutNumber as $item) {
            $ordered[] = $item;
        }

        $allowGaps = $request->boolean('allow_gaps');

        if (count($byNumber) > 0 && ! $allowGaps) {
            $nums = array_keys($byNumber);
            $min = min($nums);
            $max = max($nums);
            $start = $min <= 1 ? 1 : $min;
            $missing = [];
            for ($i = $start; $i <= $max; $i++) {
                if (! isset($byNumber[$i])) {
                    $missing[] = $i;
                }
            }

            if (count($missing) > 0) {
                $preview = count($missing) > 25
                    ? implode(', ', array_slice($missing, 0, 25)).'…'
                    : implode(', ', $missing);

                return back()->withErrors([
                    'tickets' => 'Numéros manquants : '.$preview.
                        '. Cliquez sur « Réparer les manquants » puis découpez, ou confirmez l’enregistrement partiel.',
                ]);
            }
        }

        DB::transaction(function () use ($batch, $ordered, $images) {
            $imageDir = 'tickets/batch-'.$batch->id;
            Storage::disk('public')->deleteDirectory($imageDir);

            Ticket::query()->where('ticket_batch_id', $batch->id)->delete();

            foreach ($ordered as $index => $item) {
                $position = $index + 1;
                $ticketNo = $item['ticket_no'] !== null
                    ? (string) $item['ticket_no']
                    : (string) $position;
                $imagePath = $images->storeBinary($item['binary'], $imageDir, $position, 'png');

                Ticket::create([
                    'ticket_batch_id' => $batch->id,
                    'position' => $position,
                    'username' => null,
                    'password' => null,
                    'code' => null,
                    'ticket_no' => $ticketNo,
                    'image_path' => $imagePath,
                    'raw_text' => 'Ticket #'.$ticketNo,
                    'revealed' => false,
                ]);
            }

            $batch->update([
                'total_tickets' => count($ordered),
                'current_index' => 0,
                'needs_visual_scan' => false,
            ]);
        });

        $count = count($ordered);
        $range = '';
        if (count($byNumber) > 0) {
            $nums = array_keys($byNumber);
            $missingCount = 0;
            for ($i = min($nums); $i <= max($nums); $i++) {
                if (! isset($byNumber[$i])) {
                    $missingCount++;
                }
            }
            $range = $missingCount > 0
                ? ' (n°'.min($nums).'→'.max($nums).', '.$missingCount.' trou(s) accepté(s))'
                : ' (n°'.min($nums).'→'.max($nums).', sans trou)';
        }

        return redirect()
            ->route('tickets.show', $batch)
            ->with('success', $count.' ticket(s) '.$batch->durationLabel().' prêts'.$range.'.');
    }

    public function next(TicketBatch $batch): RedirectResponse
    {
        if ($batch->isPendingVisualScan()) {
            return redirect()->route('tickets.visual', $batch);
        }

        if ($batch->isExhausted()) {
            return redirect()
                ->route('tickets.show', $batch)
                ->with('info', 'Tous les tickets de ce lot ont déjà été affichés.');
        }

        $nextPosition = $batch->current_index + 1;

        $ticket = Ticket::query()
            ->where('ticket_batch_id', $batch->id)
            ->where('position', $nextPosition)
            ->firstOrFail();

        $ticket->update([
            'revealed' => true,
            'revealed_at' => now(),
        ]);

        $batch->update([
            'current_index' => $nextPosition,
        ]);

        return redirect()
            ->route('tickets.show', $batch)
            ->with('revealed_ticket_id', $ticket->id);
    }

    public function show(TicketBatch $batch): View|RedirectResponse
    {
        if ($batch->isPendingVisualScan()) {
            return redirect()->route('tickets.visual', $batch);
        }

        $batch->load('tickets');

        $currentTicket = null;
        if ($batch->current_index > 0) {
            $currentTicket = $batch->tickets
                ->firstWhere('position', $batch->current_index);
        }

        return view('tickets.show', [
            'batch' => $batch,
            'currentTicket' => $currentTicket,
        ]);
    }

    public function destroy(TicketBatch $batch): RedirectResponse
    {
        if ($batch->stored_path) {
            Storage::disk('local')->delete($batch->stored_path);
        }

        Storage::disk('public')->deleteDirectory('tickets/batch-'.$batch->id);

        $batch->delete();

        return redirect()
            ->route('tickets.index')
            ->with('success', 'Lot '.$batch->durationLabel().' supprimé.');
    }

    /**
     * @param  array<int, array{username:?string,password:?string,code:?string,ip_address:?string,ticket_no:?string,duration:?string,price:?string,raw:string}>  $extracted
     */
    protected function storeTextTickets(string $filename, string $path, string $durationKey, array $extracted, TicketImageGenerator $images): TicketBatch
    {
        return DB::transaction(function () use ($filename, $path, $durationKey, $extracted, $images) {
            $batch = TicketBatch::create([
                'original_filename' => $filename,
                'duration_key' => $durationKey,
                'stored_path' => $path,
                'total_tickets' => count($extracted),
                'current_index' => 0,
                'needs_visual_scan' => false,
            ]);

            $imageDir = 'tickets/batch-'.$batch->id;

            foreach ($extracted as $index => $item) {
                $position = $index + 1;
                $imagePath = $images->generate($item, $imageDir, $position);

                Ticket::create([
                    'ticket_batch_id' => $batch->id,
                    'position' => $position,
                    'username' => $item['username'],
                    'password' => $item['password'],
                    'code' => $item['code'],
                    'ip_address' => $item['ip_address'] ?? null,
                    'ticket_no' => $item['ticket_no'] ?? (string) $position,
                    'duration' => $item['duration'] ?? null,
                    'price' => $item['price'] ?? null,
                    'image_path' => $imagePath,
                    'raw_text' => $item['raw'],
                    'revealed' => false,
                ]);
            }

            return $batch;
        });
    }

    /**
     * @param  array<int, string>  $binaries
     */
    protected function storeImageTickets(string $filename, string $path, string $durationKey, array $binaries, TicketImageGenerator $images): TicketBatch
    {
        return DB::transaction(function () use ($filename, $path, $durationKey, $binaries, $images) {
            $batch = TicketBatch::create([
                'original_filename' => $filename,
                'duration_key' => $durationKey,
                'stored_path' => $path,
                'total_tickets' => count($binaries),
                'current_index' => 0,
                'needs_visual_scan' => false,
            ]);

            $imageDir = 'tickets/batch-'.$batch->id;

            foreach ($binaries as $index => $binary) {
                $position = $index + 1;
                $ext = str_starts_with($binary, "\xFF\xD8") ? 'jpg' : 'png';
                $imagePath = $images->storeBinary($binary, $imageDir, $position, $ext);

                Ticket::create([
                    'ticket_batch_id' => $batch->id,
                    'position' => $position,
                    'username' => null,
                    'password' => null,
                    'code' => null,
                    'ticket_no' => (string) $position,
                    'image_path' => $imagePath,
                    'raw_text' => 'Ticket image #'.$position,
                    'revealed' => false,
                ]);
            }

            return $batch;
        });
    }

    /**
     * @param  array<int, string>  $jpegs
     * @return array<int, string>
     */
    protected function imagesToTicketBinaries(array $jpegs, TicketGridSplitter $gridSplitter, int $cols, int $rows): array
    {
        $tickets = [];

        foreach ($jpegs as $jpeg) {
            $info = @getimagesizefromstring($jpeg);
            if ($info === false) {
                continue;
            }

            [$w, $h] = $info;
            $aspect = $h > 0 ? ($w / $h) : 0;

            if ($aspect >= 1.4 && $aspect <= 2.6 && $w < 1200) {
                $tickets[] = $jpeg;
                continue;
            }

            $parts = $gridSplitter->split($jpeg, $cols, $rows);
            foreach ($parts as $part) {
                $tickets[] = $part;
            }
        }

        return $tickets;
    }

    protected function dataUrlToBinary(string $dataUrl): ?string
    {
        if (! preg_match('/^data:image\/(?:png|jpeg|jpg);base64,(.+)$/i', $dataUrl, $match)) {
            return null;
        }

        $binary = base64_decode($match[1], true);

        return ($binary !== false && $binary !== '') ? $binary : null;
    }
}

<?php

namespace App\Services;

use Smalot\PdfParser\Parser;

class PdfTicketExtractor
{
    /**
     * Extrait les tickets WiFi depuis un fichier PDF.
     *
     * @return array<int, array{username:?string,password:?string,code:?string,ip_address:?string,ticket_no:?string,duration:?string,price:?string,raw:string}>
     */
    public function extract(string $pdfPath): array
    {
        $parser = new Parser();
        $pdf = $parser->parseFile($pdfPath);
        $text = $pdf->getText();

        return $this->extractFromText($text);
    }

    /**
     * @return array<int, array{username:?string,password:?string,code:?string,ip_address:?string,ticket_no:?string,duration:?string,price:?string,raw:string}>
     */
    public function extractFromText(string $text): array
    {
        if (trim($text) === '') {
            return [];
        }

        $tickets = $this->extractMikrotikVouchers($text);

        if (count($tickets) === 0) {
            $tickets = $this->extractUserPassPairs($text);
        }

        if (count($tickets) === 0) {
            $tickets = $this->extractLabeledCodes($text);
        }

        if (count($tickets) === 0) {
            $tickets = $this->extractStandaloneCodes($text);
        }

        return $this->uniqueTickets($tickets);
    }

    /**
     * Format voucher MikroTik / Hotspot :
     * 11.11.11.1 [1]
     * Username Password
     * hgc 778
     * 3d 1h CFA 100.00
     *
     * @return array<int, array{username:?string,password:?string,code:?string,ip_address:?string,ticket_no:?string,duration:?string,price:?string,raw:string}>
     */
    protected function extractMikrotikVouchers(string $text): array
    {
        $tickets = [];

        // Bloc complet : IP + [n] + Username/Password + user + pass + durée/prix
        $blockPattern = '/(\d{1,3}(?:\.\d{1,3}){3})\s*\[(\d+)\]\s*(?:Username|User)\s*(?:Password|Pass)?\s*([A-Za-z0-9\-_.@]{2,64})\s+([A-Za-z0-9\-_.@]{2,64})\s*((?:\d+\s*[dhms]\s*)+)(?:\s*(CFA|USD|EUR|XOF|FCFA)?\s*([\d]+(?:[.,]\d{1,2})?))?/iu';

        if (preg_match_all($blockPattern, $text, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $duration = trim(preg_replace('/\s+/', ' ', $match[5]));
                $currency = strtoupper(trim($match[6] ?? 'CFA'));
                $amount = isset($match[7]) ? str_replace(',', '.', $match[7]) : null;
                $price = $amount !== null ? trim($currency.' '.number_format((float) $amount, 2, '.', '')) : null;

                $tickets[] = $this->makeTicket([
                    'username' => $match[3],
                    'password' => $match[4],
                    'ip_address' => $match[1],
                    'ticket_no' => $match[2],
                    'duration' => $duration,
                    'price' => $price,
                ]);
            }

            if (count($tickets) > 0) {
                return $tickets;
            }
        }

        // Variante : IP [n] puis user/pass sur lignes, sans labels obligatoires
        $loosePattern = '/(\d{1,3}(?:\.\d{1,3}){3})\s*\[(\d+)\][\s\S]{0,80}?([A-Za-z0-9\-_.@]{2,32})\s+([A-Za-z0-9\-_.@]{2,32})[\s\S]{0,40}?((?:\d+\s*[dhms]\s*)+)(?:\s*(CFA|USD|EUR|XOF|FCFA)?\s*([\d]+(?:[.,]\d{1,2})?))?/iu';

        if (preg_match_all($loosePattern, $text, $matches, PREG_SET_ORDER)) {
            $skip = ['username', 'password', 'login', 'ticket', 'wifi', 'user', 'pass', 'voucher'];
            foreach ($matches as $match) {
                $user = $match[3];
                $pass = $match[4];
                if (in_array(strtolower($user), $skip, true) || in_array(strtolower($pass), $skip, true)) {
                    continue;
                }

                $duration = trim(preg_replace('/\s+/', ' ', $match[5]));
                $currency = strtoupper(trim($match[6] ?? 'CFA'));
                $amount = isset($match[7]) ? str_replace(',', '.', $match[7]) : null;
                $price = $amount !== null ? trim($currency.' '.number_format((float) $amount, 2, '.', '')) : null;

                $tickets[] = $this->makeTicket([
                    'username' => $user,
                    'password' => $pass,
                    'ip_address' => $match[1],
                    'ticket_no' => $match[2],
                    'duration' => $duration,
                    'price' => $price,
                ]);
            }

            if (count($tickets) > 0) {
                return $tickets;
            }
        }

        // Fallback : paires user/pass très courtes près d'un numéro [n]
        if (preg_match_all('/\[(\d+)\]\s*(?:Username\s*)?(?:Password\s*)?([A-Za-z0-9\-_.@]{2,32})\s+([A-Za-z0-9\-_.@]{2,32})/iu', $text, $matches, PREG_SET_ORDER)) {
            $skip = ['username', 'password', 'login', 'ticket', 'wifi', 'user', 'pass', 'voucher'];
            $defaultIp = null;
            if (preg_match('/(\d{1,3}(?:\.\d{1,3}){3})/', $text, $ipMatch)) {
                $defaultIp = $ipMatch[1];
            }

            foreach ($matches as $match) {
                $user = $match[2];
                $pass = $match[3];
                if (in_array(strtolower($user), $skip, true) || in_array(strtolower($pass), $skip, true)) {
                    continue;
                }

                $tickets[] = $this->makeTicket([
                    'username' => $user,
                    'password' => $pass,
                    'ip_address' => $defaultIp,
                    'ticket_no' => $match[1],
                ]);
            }
        }

        return $tickets;
    }

    /**
     * @return array<int, array{username:?string,password:?string,code:?string,ip_address:?string,ticket_no:?string,duration:?string,price:?string,raw:string}>
     */
    protected function extractUserPassPairs(string $text): array
    {
        $tickets = [];

        $userPassPattern = '/(?:user(?:name)?|login|utilisateur|identifiant)\s*[:=\-]?\s*([A-Za-z0-9\-_.@]+)\s*(?:password|pass|mot\s*de\s*passe|mdp|pwd)\s*[:=\-]?\s*([A-Za-z0-9\-_.@]+)/iu';

        if (preg_match_all($userPassPattern, $text, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $tickets[] = $this->makeTicket([
                    'username' => $match[1],
                    'password' => $match[2],
                ]);
            }

            return $tickets;
        }

        $passUserPattern = '/(?:password|pass|mot\s*de\s*passe|mdp|pwd)\s*[:=\-]?\s*([A-Za-z0-9\-_.@]+)\s*(?:user(?:name)?|login|utilisateur|identifiant)\s*[:=\-]?\s*([A-Za-z0-9\-_.@]+)/iu';

        if (preg_match_all($passUserPattern, $text, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $tickets[] = $this->makeTicket([
                    'username' => $match[2],
                    'password' => $match[1],
                ]);
            }

            return $tickets;
        }

        $lines = preg_split('/\R+/', $text) ?: [];
        $clean = [];
        foreach ($lines as $line) {
            $line = trim(preg_replace('/\s+/', ' ', $line) ?? '');
            if ($line !== '') {
                $clean[] = $line;
            }
        }

        for ($i = 0; $i < count($clean) - 1; $i++) {
            $userMatch = null;
            $passMatch = null;

            if (preg_match('/^(?:user(?:name)?|login|utilisateur|identifiant)\s*[:=\-]?\s*(.+)$/iu', $clean[$i], $userMatch)
                && preg_match('/^(?:password|pass|mot\s*de\s*passe|mdp|pwd)\s*[:=\-]?\s*(.+)$/iu', $clean[$i + 1], $passMatch)
            ) {
                $username = trim($userMatch[1]);
                $password = trim($passMatch[1]);
                if ($this->looksLikeCredential($username) && $this->looksLikeCredential($password)) {
                    $tickets[] = $this->makeTicket([
                        'username' => $username,
                        'password' => $password,
                    ]);
                }
            }
        }

        if (count($tickets) > 0) {
            return $tickets;
        }

        // Codes courts autorisés (ex: hgc / 778)
        if (preg_match_all('/\b([A-Za-z0-9]{2,20})\s+([A-Za-z0-9]{2,20})\b/', $text, $matches, PREG_SET_ORDER)) {
            $skip = ['username', 'password', 'login', 'ticket', 'wifi', 'zone', 'voucher', 'code', 'user', 'pass', 'cfa', 'usd', 'eur'];
            foreach ($matches as $match) {
                $a = $match[1];
                $b = $match[2];
                if (in_array(strtolower($a), $skip, true) || in_array(strtolower($b), $skip, true)) {
                    continue;
                }
                if ($this->looksLikeCredential($a) && $this->looksLikeCredential($b)) {
                    $tickets[] = $this->makeTicket([
                        'username' => $a,
                        'password' => $b,
                    ]);
                }
            }
        }

        return $tickets;
    }

    /**
     * @return array<int, array{username:?string,password:?string,code:?string,ip_address:?string,ticket_no:?string,duration:?string,price:?string,raw:string}>
     */
    protected function extractLabeledCodes(string $text): array
    {
        $tickets = [];

        if (preg_match_all('/(?:code|voucher|ticket|pin|coupon)\s*[:=\-]?\s*([A-Za-z0-9\-_]{4,32})/iu', $text, $matches)) {
            foreach ($matches[1] as $code) {
                $tickets[] = $this->makeTicket([
                    'code' => $code,
                ]);
            }
        }

        return $tickets;
    }

    /**
     * @return array<int, array{username:?string,password:?string,code:?string,ip_address:?string,ticket_no:?string,duration:?string,price:?string,raw:string}>
     */
    protected function extractStandaloneCodes(string $text): array
    {
        $tickets = [];
        $lines = preg_split('/\R+/', $text) ?: [];
        $skipWords = ['username', 'password', 'login', 'ticket', 'wifi', 'zone', 'voucher', 'user', 'pass', 'page', 'total'];

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            if (preg_match('/^([A-Za-z0-9\-_]{5,32})$/', $line, $match)) {
                $code = $match[1];
                if (in_array(strtolower($code), $skipWords, true)) {
                    continue;
                }
                if (! preg_match('/[0-9]/', $code) && ! preg_match('/[A-Za-z]/', $code)) {
                    continue;
                }
                $tickets[] = $this->makeTicket([
                    'code' => $code,
                ]);
            }
        }

        return $tickets;
    }

    /**
     * @param  array{username?:?string,password?:?string,code?:?string,ip_address?:?string,ticket_no?:?string,duration?:?string,price?:?string}  $data
     * @return array{username:?string,password:?string,code:?string,ip_address:?string,ticket_no:?string,duration:?string,price:?string,raw:string}
     */
    protected function makeTicket(array $data): array
    {
        $username = $data['username'] ?? null;
        $password = $data['password'] ?? null;
        $code = $data['code'] ?? null;

        if ($username && $password) {
            $raw = "User: {$username} / Pass: {$password}";
        } else {
            $raw = $code ?? '';
        }

        return [
            'username' => $username,
            'password' => $password,
            'code' => $code,
            'ip_address' => $data['ip_address'] ?? null,
            'ticket_no' => $data['ticket_no'] ?? null,
            'duration' => $data['duration'] ?? null,
            'price' => $data['price'] ?? null,
            'raw' => $raw,
        ];
    }

    protected function looksLikeCredential(string $value): bool
    {
        $value = trim($value);
        if (strlen($value) < 2 || strlen($value) > 64) {
            return false;
        }

        return (bool) preg_match('/^[A-Za-z0-9\-_.@]+$/', $value);
    }

    /**
     * @param  array<int, array{username:?string,password:?string,code:?string,ip_address:?string,ticket_no:?string,duration:?string,price:?string,raw:string}>  $tickets
     * @return array<int, array{username:?string,password:?string,code:?string,ip_address:?string,ticket_no:?string,duration:?string,price:?string,raw:string}>
     */
    protected function uniqueTickets(array $tickets): array
    {
        $seen = [];
        $unique = [];

        foreach ($tickets as $ticket) {
            $key = strtolower(($ticket['username'] ?? '').'|'.($ticket['password'] ?? '').'|'.($ticket['code'] ?? '').'|'.($ticket['ticket_no'] ?? ''));
            if (isset($seen[$key]) || $key === '|||') {
                continue;
            }
            $seen[$key] = true;
            $unique[] = $ticket;
        }

        return $unique;
    }
}

<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;

class TicketImageGenerator
{
    /**
     * Génère une image SVG du voucher (même style que les tickets MikroTik).
     *
     * @param  array{username:?string,password:?string,code:?string,ip_address:?string,ticket_no:?string,duration:?string,price:?string,raw?:string}  $ticket
     */
    public function generate(array $ticket, string $relativeDir, int $position): string
    {
        $ip = $this->escape($ticket['ip_address'] ?? '—');
        $no = $this->escape($ticket['ticket_no'] ?? (string) $position);
        $user = $this->escape($ticket['username'] ?? $ticket['code'] ?? '—');
        $pass = $this->escape($ticket['password'] ?? ($ticket['username'] ? '—' : ($ticket['code'] ?? '—')));
        $duration = trim((string) ($ticket['duration'] ?? ''));
        $price = trim((string) ($ticket['price'] ?? ''));
        $footer = $this->escape(trim($duration.' '.$price) !== '' ? trim($duration.' '.$price) : '—');

        $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="420" height="220" viewBox="0 0 420 220">
  <rect width="420" height="220" fill="#ffffff"/>
  <rect x="12" y="12" width="396" height="196" fill="none" stroke="#111111" stroke-width="2.5"/>
  <line x1="12" y1="52" x2="408" y2="52" stroke="#111111" stroke-width="1.5"/>
  <text x="28" y="40" font-family="Arial, Helvetica, sans-serif" font-size="22" font-weight="700" fill="#111111">{$ip}</text>
  <text x="392" y="40" text-anchor="end" font-family="Arial, Helvetica, sans-serif" font-size="20" font-weight="700" fill="#111111">[{$no}]</text>
  <text x="90" y="78" text-anchor="middle" font-family="Arial, Helvetica, sans-serif" font-size="15" fill="#222222">Username</text>
  <text x="300" y="78" text-anchor="middle" font-family="Arial, Helvetica, sans-serif" font-size="15" fill="#222222">Password</text>
  <rect x="28" y="88" width="160" height="52" fill="none" stroke="#111111" stroke-width="2"/>
  <rect x="232" y="88" width="160" height="52" fill="none" stroke="#111111" stroke-width="2"/>
  <text x="108" y="122" text-anchor="middle" font-family="Arial, Helvetica, sans-serif" font-size="26" font-weight="700" fill="#111111">{$user}</text>
  <text x="312" y="122" text-anchor="middle" font-family="Arial, Helvetica, sans-serif" font-size="26" font-weight="700" fill="#111111">{$pass}</text>
  <rect x="28" y="154" width="364" height="40" fill="none" stroke="#111111" stroke-width="2"/>
  <text x="210" y="181" text-anchor="middle" font-family="Arial, Helvetica, sans-serif" font-size="18" font-weight="600" fill="#111111">{$footer}</text>
</svg>
SVG;

        $filename = sprintf('ticket-%03d.svg', $position);
        $path = trim($relativeDir, '/').'/'.$filename;
        Storage::disk('public')->put($path, $svg);

        return $path;
    }

    /**
     * Enregistre une image binaire (PNG/JPEG) comme ticket.
     */
    public function storeBinary(string $binary, string $relativeDir, int $position, string $extension = 'png'): string
    {
        $extension = ltrim(strtolower($extension), '.');
        if (! in_array($extension, ['png', 'jpg', 'jpeg', 'webp', 'svg'], true)) {
            $extension = 'png';
        }

        $filename = sprintf('ticket-%03d.%s', $position, $extension === 'jpeg' ? 'jpg' : $extension);
        $path = trim($relativeDir, '/').'/'.$filename;
        Storage::disk('public')->put($path, $binary);

        return $path;
    }

    protected function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}

@props(['ticket'])

<div class="voucher-wrap">
    @if ($ticket->imageUrl())
        <img
            src="{{ $ticket->imageUrl() }}"
            alt="Ticket {{ $ticket->ticket_no ?? $ticket->position }}"
            class="voucher-img"
        >
    @else
        <div class="voucher-fallback" role="img" aria-label="Ticket {{ $ticket->ticket_no ?? $ticket->position }}">
            <div class="voucher-top">
                <span>{{ $ticket->ip_address ?? '—' }}</span>
                <span>[{{ $ticket->ticket_no ?? $ticket->position }}]</span>
            </div>
            <div class="voucher-labels">
                <span>Username</span>
                <span>Password</span>
            </div>
            <div class="voucher-creds">
                <div class="box">{{ $ticket->username ?? $ticket->code ?? '—' }}</div>
                <div class="box">{{ $ticket->password ?? '—' }}</div>
            </div>
            <div class="voucher-footer">
                {{ trim(($ticket->duration ?? '').' '.($ticket->price ?? '')) ?: '—' }}
            </div>
        </div>
    @endif
</div>

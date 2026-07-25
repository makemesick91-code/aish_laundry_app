@extends('tracking.layout')

{{--
    THE PORTAL PAGE (FR-089).

    Every value rendered here comes from PublicTrackingProjection, which is an
    ALLOW-LIST read model with masking applied at BUILD time (TRK-008, TRK-018).
    This template therefore cannot leak a full address, a full phone, an internal
    note, another order, or a photograph — not because it filters them out, but
    because the array it receives never contained them.

    Blade's `{{ }}` escapes by default, so a tenant-supplied outlet name or a
    service name containing markup renders as text (T7-15). There is no `{!! !!}`
    anywhere on this surface and there must never be.
--}}

@section('content')
    <div class="card">
        <h1>Pesanan {{ $tracking['order_number'] }}</h1>
        <p class="muted small">
            {{ $tracking['brand']['name'] }}@if ($tracking['outlet']['name'] !== '') — {{ $tracking['outlet']['name'] }}@endif
        </p>

        {{-- Status is TEXT first. Colour is reinforcement only (Rule 27). --}}
        <p><span class="status">{{ $tracking['status']['label'] }}</span></p>

        @if ($tracking['is_ready_for_pickup'])
            <p class="notice">
                Cucian Anda sudah siap diambil di outlet. Bawa nomor pesanan
                <strong>{{ $tracking['order_number'] }}</strong> saat mengambil.
            </p>
        @endif

        <dl>
            <dt>Pelanggan</dt>
            <dd>{{ $tracking['customer']['masked_name'] }}</dd>

            @if ($tracking['customer']['masked_phone'] !== '')
                <dt>Telepon</dt>
                <dd>{{ $tracking['customer']['masked_phone'] }}</dd>
            @endif

            @if ($tracking['service_types'] !== [])
                <dt>Layanan</dt>
                <dd>{{ implode(', ', $tracking['service_types']) }}</dd>
            @endif

            <dt>Status pembayaran</dt>
            <dd>{{ $tracking['payment_state']['label'] }}</dd>

            <dt>Sisa yang harus dibayar</dt>
            {{-- Integer Rupiah, formatted for display only. The value itself is
                 never computed here; it is read from the Step 5 ledger (Rule 04). --}}
            <dd class="amount">Rp{{ number_format(max((int) $tracking['amount_due_rupiah'], 0), 0, ',', '.') }}</dd>

            @if ($tracking['estimated_completion'] !== null)
                <dt>Perkiraan selesai</dt>
                {{-- Labelled an ESTIMATE, always. The product never presents an
                     estimate as a guarantee (Rule 09 hard rule 1). --}}
                <dd>{{ $tracking['estimated_completion'] }} <span class="muted small">(perkiraan, bukan jaminan)</span></dd>
            @endif
        </dl>
    </div>

    @if ($tracking['status_history'] !== [])
        <div class="card">
            <h2>Riwayat</h2>
            <ol class="timeline">
                @foreach ($tracking['status_history'] as $entry)
                    <li>
                        <strong>{{ $entry['label'] }}</strong>
                        <span class="muted small">{{ $entry['occurred_at'] }}</span>
                    </li>
                @endforeach
            </ol>
        </div>
    @endif

    @if ($tracking['available_actions'] !== [])
        <div class="card">
            <h2>Butuh perubahan?</h2>
            {{-- NO controls are rendered for these actions in Step 7, and that is
                 deliberate rather than an omission. The OTP gate exists (FR-091),
                 but the EFFECT of changing a delivery address or a schedule is
                 Step 8 workflow that does not exist yet. Rendering a button that
                 does nothing would be the dead control Rule 34 rejects; building
                 the effect would be the scope leak DEC-0039 §5 forbids. So the
                 page states the route that actually works today. --}}
            <p class="small">
                Untuk mengubah alamat pengantaran atau meminta perubahan jadwal, hubungi outlet
                @if ($tracking['outlet']['name'] !== ''){{ $tracking['outlet']['name'] }} @endif
                dengan menyebutkan nomor pesanan <strong>{{ $tracking['order_number'] }}</strong>.
                Outlet akan meminta kode verifikasi ke nomor WhatsApp Anda sebelum memprosesnya.
            </p>
        </div>
    @endif
@endsection

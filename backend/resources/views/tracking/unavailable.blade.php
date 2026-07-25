@extends('tracking.layout')

{{--
    THE ONE NOT-AVAILABLE PAGE.

    Rendered identically for an unknown token, a malformed one, an expired one, a
    revoked one, a superseded one, and a throttled request. There is no variable in
    this template and no branch: six different causes produce one byte-identical
    page, which is what stops the portal from answering "does this order exist?"
    (TRK-007, AC-07-02, Rule 48 hard rule 5).

    It states the RECOVERY ACTION rather than an error code, because a customer
    holding a dead link needs to know what to do next, not what failed internally
    (Rule 29 hard rule 9, TRACKING_ACCESS_LIFECYCLE §4.2).
--}}

@section('content')
    <div class="card">
        <h1>Tautan tidak dapat dibuka</h1>
        <p>
            Tautan pelacakan ini tidak dapat dibuka. Tautan mungkin sudah tidak berlaku,
            sudah diganti, atau salah ketik.
        </p>
        <p class="notice">
            <strong>Yang bisa Anda lakukan:</strong> minta tautan baru dari outlet tempat Anda
            menitipkan cucian, dengan menyebutkan nomor pesanan pada nota Anda.
        </p>
        <p class="small muted">
            Anda tidak perlu memasang aplikasi apa pun untuk melacak cucian Anda.
        </p>
    </div>
@endsection

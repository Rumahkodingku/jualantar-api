@extends('communications::email.layouts.email')

@section('content')
    <h1 style="font-size:20px;margin:0 0 16px;">{{ $title ?? config('app.name') }}</h1>
    <p style="font-size:14px;line-height:1.6;margin:0;">{{ $body ?? '' }}</p>
@endsection

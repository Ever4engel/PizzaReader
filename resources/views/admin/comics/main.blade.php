@extends('layouts.admin')
@section('content')
    @yield('information')
    <div class="card">
        <div class="card-header">
            <div class="row">
                <div class="col-sm-9">
                    <h3 class="mt-1 float-start">@yield('list-title')</h3>
                    @if(Auth::user()->hasPermission("manager"))
                        @yield('list-buttons')
                    @endif
                </div>
                <div class="col-sm-3 mt-2 mt-sm-0">
                    @include('partials.card-search')
                </div>
            </div>
        </div>
        <div class="card-body">
            @yield('list')
        </div>
    </div>
@endsection

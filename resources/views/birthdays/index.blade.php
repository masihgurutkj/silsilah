@extends('layouts.app')

@section('title', __('user.upcoming_birthday'))

@section('content')

<div class="row">
    <div class="col-md-8 col-md-offset-2">
        <div class="panel panel-default text-center">
            <div class="panel-heading text-left">
                <h3 class="panel-title">{{ __('birthday.upcoming') }}</h3>
            </div>
            <table class="table table-condensed">
                <thead>
                    <tr>
                        <td>#</td>
                        <td class="text-left">{{ __('user.name') }}</td>
                        <td>{{ __('birthday.birthday') }}</td>
                        <td>{{ __('user.age') }}</td>
                    </tr>
                </thead>
                <tbody>
                    @php
                        $no = 1;
                    @endphp
                    @forelse($users as $key => $user)
                    <tr>
                        <td>{{ $no++ }}</td>
                        <td class="text-left">{{ $user->name }}</td>
                        <td>
                            {{ $user->birthday->format('j M') }}
                            ({{ __('birthday.remaining', ['count' => $user->birthday_remaining]) }})
                        </td>
                        <td>{{ __('birthday.age_years', ['age' => $user->age]) }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4">{{ __('user.no_upcoming_birthday', ['days' => 60]) }}</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
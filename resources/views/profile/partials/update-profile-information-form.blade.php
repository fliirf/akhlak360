<form id="send-verification" method="post" action="{{ route('verification.send') }}">
    @csrf
</form>

<form method="post" action="{{ route('profile.update') }}">
    @csrf
    @method('patch')

    <div class="form-group">
        <label for="name">Nama</label>
        <input id="name" name="name" type="text" value="{{ old('name', $user->name) }}"
            class="form-control @error('name') is-invalid @enderror" required autofocus autocomplete="name">
        @error('name')
            <span class="invalid-feedback">{{ $message }}</span>
        @enderror
    </div>

    <div class="form-group">
        <label for="email">Email</label>
        <input id="email" name="email" type="email" value="{{ old('email', $user->email) }}"
            class="form-control @error('email') is-invalid @enderror" required autocomplete="username">
        @error('email')
            <span class="invalid-feedback">{{ $message }}</span>
        @enderror

        @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
            <div class="alert alert-warning mt-3 mb-0">
                <i class="fas fa-envelope mr-1"></i>
                {{ __('Your email address is unverified.') }}
                <button type="submit" form="send-verification" class="btn btn-link btn-sm p-0 align-baseline">
                    {{ __('Click here to re-send the verification email.') }}
                </button>

                @if (session('status') === 'verification-link-sent')
                    <div class="mt-2">
                        <span class="badge badge-success">{{ __('A new verification link has been sent to your email address.') }}</span>
                    </div>
                @endif
            </div>
        @endif
    </div>

    <button type="submit" class="btn btn-primary">
        <i class="fas fa-save mr-1"></i> Simpan Profil
    </button>

    @if (session('status') === 'profile-updated')
        <span class="badge badge-success ml-2">{{ __('Saved.') }}</span>
    @endif
</form>

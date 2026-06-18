<x-guest-layout>
    <x-slot name="title">AKHLAK360 | Company SSO</x-slot>

    <x-slot name="brand">
        <div class="auth-brand-copy">
            <p class="text-lg font-semibold text-gray-900 leading-tight">AKHLAK360</p>
            <p class="auth-brand-subtitle text-sm text-gray-600 leading-snug">Sistem Penilaian 360° Core Values AKHLAK</p>
            <p class="auth-brand-company text-xs font-medium text-gray-500">PT Energi Nusantara</p>
        </div>
    </x-slot>

    <div class="auth-sso-card">
        <div class="text-center">
            <h1 class="text-xl font-semibold text-gray-900">Company SSO</h1>
            <p class="text-sm text-gray-600 leading-6">
                Gunakan identitas perusahaan yang tersinkronisasi dari HRIS.
            </p>
            <p class="text-xs text-gray-500 leading-5">
                Simulasi akademik - bukan integrasi identity provider produksi.
            </p>
        </div>

        <form method="POST" action="{{ route('sso.authenticate') }}" class="auth-login-form mt-6" data-sso-form>
            @csrf

            <div class="auth-field">
                <x-input-label for="identity" value="Email Perusahaan atau Nomor Pegawai" />
                <x-text-input
                    id="identity"
                    class="block w-full"
                    type="text"
                    name="identity"
                    :value="old('identity')"
                    placeholder="nama@perusahaan.com atau EMP001"
                    required
                    autofocus
                    autocomplete="username"
                />
                <x-input-error :messages="$errors->get('identity')" class="auth-error text-sm text-red-600" />
            </div>

            <div class="auth-field">
                <x-input-label for="simulation_code" value="Kode SSO Personal" />
                <x-text-input
                    id="simulation_code"
                    class="block w-full"
                    type="password"
                    name="simulation_code"
                    placeholder="Masukkan kode personal Anda"
                    required
                    autocomplete="off"
                />
                <x-input-error :messages="$errors->get('simulation_code')" class="auth-error text-sm text-red-600" />
            </div>

            <x-primary-button class="w-full justify-center" data-sso-button>
                <span data-sso-button-text>Masuk dengan Company SSO</span>
            </x-primary-button>
        </form>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const form = document.querySelector('[data-sso-form]');
            const button = document.querySelector('[data-sso-button]');
            const text = document.querySelector('[data-sso-button-text]');

            form?.addEventListener('submit', function () {
                button.disabled = true;
                button.classList.add('opacity-75', 'cursor-not-allowed');
                text.textContent = 'Memproses...';
            });
        });
    </script>
</x-guest-layout>

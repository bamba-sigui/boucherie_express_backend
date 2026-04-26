<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion — Boucherie Express</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-gray-900 flex items-center justify-center px-4">
    <div class="w-full max-w-md">
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-16 h-16 bg-red-600 rounded-full mb-4">
                <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"/>
                </svg>
            </div>
            <h1 class="text-3xl font-bold text-white tracking-tight">Boucherie Express</h1>
            <p class="text-gray-400 mt-1 text-sm">Espace Administration</p>
        </div>

        <div class="bg-gray-800 rounded-2xl shadow-2xl border border-gray-700 p-8">
            <h2 class="text-xl font-semibold text-white mb-1">Connexion</h2>
            <p class="text-gray-500 text-sm mb-6">Accès réservé aux administrateurs et partenaires</p>

            @if ($errors->any())
                <div class="mb-5 p-3 bg-red-900/40 border border-red-500/50 rounded-lg text-red-300 text-sm">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="/login" class="space-y-5">
                @csrf

                <div>
                    <label for="email" class="block text-sm font-medium text-gray-300 mb-1.5">
                        Adresse e-mail
                    </label>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        value="{{ old('email') }}"
                        required
                        autocomplete="email"
                        class="w-full px-4 py-2.5 bg-gray-700 border border-gray-600 rounded-lg text-white
                               placeholder-gray-500 focus:outline-none focus:border-red-500
                               focus:ring-1 focus:ring-red-500 transition-colors"
                        placeholder="admin@boucherie-express.fr"
                    >
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium text-gray-300 mb-1.5">
                        Mot de passe
                    </label>
                    <input
                        type="password"
                        id="password"
                        name="password"
                        required
                        autocomplete="current-password"
                        class="w-full px-4 py-2.5 bg-gray-700 border border-gray-600 rounded-lg text-white
                               placeholder-gray-500 focus:outline-none focus:border-red-500
                               focus:ring-1 focus:ring-red-500 transition-colors"
                        placeholder="••••••••"
                    >
                </div>

                <div class="flex items-center gap-2">
                    <input
                        type="checkbox"
                        id="remember"
                        name="remember"
                        class="w-4 h-4 rounded border-gray-600 bg-gray-700 text-red-600
                               focus:ring-red-500 focus:ring-offset-gray-800"
                    >
                    <label for="remember" class="text-sm text-gray-400 cursor-pointer">
                        Se souvenir de moi
                    </label>
                </div>

                <button
                    type="submit"
                    class="w-full py-2.5 bg-red-600 hover:bg-red-700 active:bg-red-800
                           text-white font-semibold rounded-lg transition-colors duration-200
                           focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2
                           focus:ring-offset-gray-800"
                >
                    Se connecter
                </button>
            </form>
        </div>

        <p class="text-center text-gray-600 text-xs mt-6">
            &copy; {{ date('Y') }} Boucherie Express — Tous droits réservés
        </p>
    </div>
</body>
</html>

<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WelcomeController extends Controller
{
    /**
     * Show the public page where agencies and owners get to know the app, or the dashboard to
     * someone already signed in.
     */
    public function __invoke(Request $request): Response|RedirectResponse
    {
        if ($request->user() !== null) {
            return to_route('dashboard');
        }

        return Inertia::render('Welcome', [
            'salesWhatsapp' => config('app.sales_whatsapp'),
        ])->withViewData(['isWelcome' => true, 'meta' => [
            'title' => __('Site para imobiliárias e proprietários de imóveis').' - '.config('app.name'),
            'description' => __('Cobrança, multa e juros, repasse e extrato do proprietário num sistema só, com funil de vendas e vitrine de imóveis.'),
            'image' => null,
            'url' => route('home'),
        ]]);
    }
}

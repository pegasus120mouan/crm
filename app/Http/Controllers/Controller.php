<?php

namespace App\Http\Controllers;

use App\Models\Boutique;
use App\Models\Commande;
use App\Models\Utilisateur;

abstract class Controller
{
    protected function commercialId(): int
    {
        $id = (int) data_get(session('utilisateur'), 'id', 0);

        abort_unless($id > 0, 403);

        return $id;
    }

    protected function authorizeOwnedClient(Utilisateur $client): void
    {
        abort_unless(
            $client->role === 'clients' && (int) $client->commercial_id === $this->commercialId(),
            404
        );
    }

    protected function authorizeOwnedBoutique(Boutique $boutique): void
    {
        abort_unless((int) $boutique->commercial_id === $this->commercialId(), 404);
    }

    protected function authorizeOwnedCommande(Commande $commande): void
    {
        abort_unless((int) $commande->client?->commercial_id === $this->commercialId(), 404);
    }
}

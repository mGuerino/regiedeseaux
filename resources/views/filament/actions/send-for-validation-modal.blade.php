<div class="space-y-3 text-sm">
    <p>
        L'attestation de la demande <strong>{{ $reference }}</strong> va être générée,
        puis soumise à validation.
    </p>

    @if ($supervisors->isEmpty())
        <p class="text-danger-600 dark:text-danger-400">
            Aucun superviseur n'est désigné. Cochez « Superviseur » sur un utilisateur
            disposant d'une adresse email (Administration → Utilisateurs) avant d'envoyer
            cette attestation en validation.
        </p>
    @endif
</div>

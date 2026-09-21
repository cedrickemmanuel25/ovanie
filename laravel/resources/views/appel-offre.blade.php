@extends('layouts.guest')

@section('title', 'Déposer un appel d\'offre')

@section('styles')
  <link rel="stylesheet" href="{{ asset('css/appel-offre.css') }}" />
@endsection

@section('content')
  <div class="form-container">
    <h2>Déposer un appel d'offre</h2>

    <form id="appelForm" action="{{ route('appel.offre.store') }}" method="POST" enctype="multipart/form-data">
      @csrf

      <label for="secteur">Secteur d'activité *</label>
      <select id="secteur" name="secteur" required>
        <option value="">-- Sélectionner un secteur --</option>
      </select>

      <label>Services / Travaux demandés (plusieurs choix possibles) *</label>
      <div id="servicesContainer" class="checkbox-group"></div>

      <label for="category">Catégorie *</label>
      <select id="category" name="category" required>
        <option value="">-- Sélectionner une catégorie --</option>
        <option value="projet-participatif">Projet participatif</option>
        <option value="bail-construction">Bail à construction</option>
        <option value="projet-construction">Projet de construction</option>
        <option value="travaux-speciaux">Travaux spéciaux</option>
        <option value="reste-travaux">Reste de travaux</option>
        <option value="vente-location">Vente / location (terrain/maison)</option>
      </select>

      <div class="row">
        <div>
          <label for="prenom">Prénom *</label>
          <input type="text" id="prenom" name="prenom" required />
        </div>
        <div>
          <label for="nom">Nom *</label>
          <input type="text" id="nom" name="nom" required />
        </div>
      </div>

      <label for="email">Email *</label>
      <input type="email" id="email" name="email" required />

      <label for="telephone">Téléphone</label>
      <input type="tel" id="telephone" name="telephone" />

      <div class="row">
        <div>
          <label for="pays">Pays</label>
          <input type="text" id="pays" name="pays" value="Côte d’Ivoire" readonly />
        </div>
        <div>
          <label for="ville">Ville</label>
          <input type="text" id="ville" name="ville" />
        </div>
      </div>

      <label for="image">Image vitrine</label>
      <input type="file" id="image" name="image" accept="image/*" />

      <label for="budget">Budget estimé (FCFA) *</label>
      <input type="number" id="budget" name="budget" min="0" step="1000" placeholder="Ex : 1500000" required />

      <label for="delai">Délai souhaité (en jours)</label>
      <input type="number" id="delai" name="delai" min="1" step="1" placeholder="Ex : 30" />

      <label for="description">Description détaillée *</label>
      <textarea id="description" name="description" placeholder="Précisez vos besoins, contraintes, conditions…"
        required></textarea>

      <small class="error" id="errorMessage"></small>

      <p class="info">
        Les appels d'offres doivent respecter les conditions générales.<br>
        Merci de fournir des informations complètes et précises.
      </p>

      <p class="legal">
        Les données recueillies sont utilisées pour la gestion des appels d'offres.<br>
        Vous avez un droit d’accès, de modification et de suppression.
      </p>

      <button type="submit">Soumettre l'appel d'offre</button>
    </form>
  </div>
@endsection

@section('scripts')
  <script>
    window.AppUser = {
      id: {{ auth()->id() ?? 'null' }},
      name: @json(auth()->user()->name ?? null),
      email: @json(auth()->user()->email ?? null)
    };
  </script>

  <script src="{{ asset('js/appel-offre.js') }}" defer></script>
@endsection

String cleanOvanieText(String? raw, {String fallbackSlug = ''}) {
  if (raw == null) return _titleFromSlug(fallbackSlug);

  var value = raw.trim();
  if (value.isEmpty) return _titleFromSlug(fallbackSlug);

  const replacements = <String, String>{
    'Ã©': 'é',
    'Ã¨': 'è',
    'Ãª': 'ê',
    'Ã ': 'à',
    'Ã¢': 'â',
    'Ã´': 'ô',
    'Ã®': 'î',
    'Ã¯': 'ï',
    'Ã§': 'ç',
    'Ã‰': 'É',
    'â€™': '’',
    'â€“': '–',
    'â€”': '—',
    'Å“': 'œ',
    '??lectricit??': 'Électricité',
    '??lectricit?': 'Électricité',
    '?lectricit??': 'Électricité',
    '?lectricit?': 'Électricité',
    '??nergie': 'Énergie',
    '?nergie': 'Énergie',
    'Mat??riaux': 'Matériaux',
    'mat??riaux': 'matériaux',
    'Mat?riaux': 'Matériaux',
    'mat?riaux': 'matériaux',
    '??quipements': 'Équipements',
    '??quipement': 'Équipement',
    '?quipements': 'Équipements',
    '?quipement': 'Équipement',
    's??curit??': 'sécurité',
    'S??curit??': 'Sécurité',
    's?curit?': 'sécurité',
    'S?curit?': 'Sécurité',
    '??cologique': 'Écologique',
    '?cologique': 'Écologique',
    'r??novation': 'rénovation',
    'R??novation': 'Rénovation',
    'r??servoir': 'réservoir',
    'R??servoir': 'Réservoir',
    'r?servoir': 'réservoir',
    'R?servoir': 'Réservoir',
    'c??ramique': 'céramique',
    'C??ramique': 'Céramique',
    'c?ramique': 'céramique',
    'C?ramique': 'Céramique',
    'c??rame': 'cérame',
    'C??rame': 'Cérame',
    'c?rame': 'cérame',
    'C?rame': 'Cérame',
    'gr??s': 'grès',
    'Gr??s': 'Grès',
    'gr?s': 'grès',
    'Gr?s': 'Grès',
    '??vacuation': 'évacuation',
    '?vacuation': 'évacuation',
    '?? poser': 'à poser',
    '? poser': 'à poser',
    'gros ??uvre': 'gros œuvre',
    'gros ?uvre': 'gros œuvre',
    'Gros ??uvre': 'Gros œuvre',
    'Gros ?uvre': 'Gros œuvre',
    'fa??ence': 'faïence',
    'Fa??ence': 'Faïence',
    'fa?ence': 'faïence',
    'Fa?ence': 'Faïence',
    'pr??commande': 'précommande',
    'Pr??commande': 'Précommande',
    'pr?commande': 'précommande',
    'Pr?commande': 'Précommande',
    'Cit??': 'Cité',
    'cit??': 'cité',
    'Cit?': 'Cité',
    'cit?': 'cité',
    'Kess??': 'Kessé',
    'kess??': 'kessé',
    'Kess?': 'Kessé',
    'kess?': 'kessé',
  };

  replacements.forEach((from, to) {
    value = value.replaceAll(from, to);
  });

  // Dimensions corrompues : "15 ?? 20 ?? 40 cm" -> "15 × 20 × 40 cm".
  final dimensionPattern = RegExp(r'(\d)\s*\?{1,3}\s*(\d)');
  var previous = '';
  while (previous != value && dimensionPattern.hasMatch(value)) {
    previous = value;
    value = value.replaceAllMapped(
      dimensionPattern,
      (match) => '${match.group(1)} × ${match.group(2)}',
    );
  }

  // Quelques formulations fréquentes des fiches produit OVANIE.
  value = value.replaceAll(RegExp(r'\bL\s+\?{1,2}\s+roue\b', caseSensitive: false), 'L à roue');
  value = value.replaceAll(RegExp(r'\bWC\s+\?{1,2}\s+poser\b', caseSensitive: false), 'WC à poser');

  // Séparateurs perdus entre deux groupes de mots : "DN100 ??? longueur".
  value = value.replaceAll(RegExp(r'\s+\?{2,4}\s+'), ' – ');

  // Un "?" restant dans une donnée catalogue correspond à un caractère perdu
  // lors d'un ancien import. On ne l'affiche pas au client mobile.
  value = value.replaceAll(RegExp(r'\?+'), ' ');
  value = value.replaceAll(RegExp(r'\s+'), ' ').trim();

  if (value.isEmpty && fallbackSlug.isNotEmpty) {
    return _titleFromSlug(fallbackSlug);
  }

  return value;
}

String _titleFromSlug(String slug) {
  final value = slug.trim();
  if (value.isEmpty) return '';

  final words = value
      .split(RegExp(r'[-_]+'))
      .where((part) => part.trim().isNotEmpty)
      .map((part) => part.trim())
      .toList();

  if (words.isEmpty) return '';

  final joined = words.join(' ');
  return joined[0].toUpperCase() + joined.substring(1);
}

String canonicalCategoryName({required String slug, required String rawName}) {
  final normalizedSlug = slug.toLowerCase().trim();

  if (normalizedSlug.contains('materiaux-gros-oeuvre') ||
      (normalizedSlug.contains('materiaux') && normalizedSlug.contains('gros'))) {
    return 'Matériaux gros œuvre';
  }
  if (normalizedSlug.contains('materiaux-ecologique') || normalizedSlug.contains('ecologique')) {
    return 'Matériaux écologiques';
  }
  if (normalizedSlug.contains('materiaux-de-finition') || normalizedSlug.contains('finition')) {
    return 'Matériaux de finition';
  }
  if (normalizedSlug.contains('outillage-equipement') || normalizedSlug.contains('outillage')) {
    return 'Outillage & Équipement';
  }
  if (normalizedSlug.contains('equipements-de-chantier') ||
      (normalizedSlug.contains('equipement') && normalizedSlug.contains('chantier'))) {
    return 'Équipements de chantier';
  }
  if (normalizedSlug.contains('energie-solaire') ||
      (normalizedSlug.contains('energie') && normalizedSlug.contains('solaire'))) {
    return 'Énergie solaire';
  }
  if (normalizedSlug.contains('electricite-plomberie') ||
      (normalizedSlug.contains('electricite') && normalizedSlug.contains('plomberie'))) {
    return 'Électricité & Plomberie';
  }
  if (normalizedSlug.contains('carte-cadeau')) {
    return 'Carte cadeau Ovanie';
  }
  if (normalizedSlug.contains('recondition')) {
    return 'Nos reconditionnés';
  }
  if (normalizedSlug.contains('sanitaire') || normalizedSlug.contains('wc')) {
    return 'WC & Sanitaires';
  }
  if (normalizedSlug.contains('tuyau')) {
    return 'Tuyaux';
  }
  if (normalizedSlug.contains('peinture')) {
    return 'Peinture';
  }
  if (normalizedSlug.contains('securite')) {
    return 'Sécurité';
  }

  return cleanOvanieText(rawName, fallbackSlug: slug);
}

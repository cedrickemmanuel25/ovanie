import 'dart:io';
import 'dart:math' as math;

import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:geolocator/geolocator.dart';
import 'package:image_picker/image_picker.dart';

import '../../core/network/api_client.dart';
import '../../core/ui/vendor_mockup_ui.dart';
import '../../core/ui/vendor_wizard_ui.dart';
import '../../data/vendor_repository.dart';
import '../auth/vendor_session.dart';
import '../shell/vendor_shell.dart';

class _PhoneSpacingFormatter extends TextInputFormatter {
  @override
  TextEditingValue formatEditUpdate(TextEditingValue oldValue, TextEditingValue newValue) {
    final rawDigits = newValue.text.replaceAll(RegExp(r'[^0-9]'), '');
    final digits = rawDigits.length > 10 ? rawDigits.substring(0, 10) : rawDigits;
    final buffer = StringBuffer();
    for (var i = 0; i < digits.length; i++) {
      if (i > 0 && i % 2 == 0) buffer.write(' ');
      buffer.write(digits[i]);
    }
    final formatted = buffer.toString();
    return TextEditingValue(
      text: formatted,
      selection: TextSelection.collapsed(offset: formatted.length),
    );
  }
}

String _cleanUiText(Object? raw) {
  var value = '${raw ?? ''}'.trim();
  if (value.isEmpty) return value;
  const replacements = <String, String>{
    'Adjami??': 'Adjamé',
    'Adjam??': 'Adjamé',
    'Akou??-Sant??': 'Akoué-Santé',
    'Carri??re': 'Carrière',
    'R??sidentiel': 'Résidentiel',
    'R??sidentielle': 'Résidentielle',
    'Cit??': 'Cité',
    'March??': 'Marché',
    'Universit??': 'Université',
    'R??publique': 'République',
    'Ã©': 'é',
    'Ã¨': 'è',
    'Ãª': 'ê',
    'Ã«': 'ë',
    'Ã ': 'à',
    'Ã¢': 'â',
    'Ã´': 'ô',
    'Ã¹': 'ù',
    'Ã»': 'û',
    'Ã§': 'ç',
  };
  replacements.forEach((from, to) => value = value.replaceAll(from, to));
  return value.replaceAll(RegExp(r'\s+'), ' ').trim();
}

String _ciLocalPhone(Object? raw) {
  var digits = '${raw ?? ''}'.replaceAll(RegExp(r'[^0-9]'), '');
  if (digits.startsWith('225') && digits.length >= 13) {
    digits = digits.substring(3);
  }
  if (digits.length > 10) digits = digits.substring(digits.length - 10);
  return digits;
}

String _fileName(File? file) =>
    file == null ? '' : file.path.split(Platform.pathSeparator).last;

/// Étapes de l'assistant, dans l'ordre des maquettes.
enum _Step {
  personal(
    'Informations personnelles',
    'Renseignez vos informations personnelles pour commencer la création de votre boutique.',
    footer: 'Les informations demandées sont sécurisées.',
  ),
  shop(
    'Informations de la boutique',
    'Renseignez les informations principales de votre boutique pour continuer.',
    footer: 'Les informations de votre boutique seront utilisées pour organiser votre activité et la logistique.',
  ),
  identity(
    'Vérification d’identité',
    'Ces informations nous permettent de vérifier votre identité et de sécuriser votre compte vendeur.',
    icon: Icons.badge_rounded,
  ),
  payout(
    'Paiement vendeur',
    'Choisissez votre mode de reversement et renseignez les informations de paiement pour recevoir vos paiements vendeur.',
    icon: Icons.account_balance_wallet_rounded,
  ),
  terms(
    'Conditions vendeur',
    'Veuillez lire et accepter les conditions pour finaliser l’ouverture de votre boutique.',
    icon: Icons.description_rounded,
  );

  const _Step(this.title, this.subtitle, {this.icon, this.footer});
  final String title;
  final String subtitle;

  /// Pictogramme rond affiché à gauche du titre.
  final IconData? icon;

  /// Ligne de réassurance affichée sous la carte.
  final String? footer;
}

class ShopOnboardingScreen extends StatefulWidget {
  const ShopOnboardingScreen({super.key, this.previewStep});

  /// Aperçu de design uniquement : ouvre directement l'étape indiquée (à partir de 0).
  @visibleForTesting
  final int? previewStep;

  @override
  State<ShopOnboardingScreen> createState() => _ShopOnboardingScreenState();
}

class _ShopOnboardingScreenState extends State<ShopOnboardingScreen> {
  static final int _lastStep = _Step.values.length - 1;

  final _repo = VendorRepository.instance;
  final _scrollController = ScrollController();
  final _cardKey = GlobalKey();
  final _forms = List.generate(_Step.values.length, (_) => GlobalKey<FormState>());
  final _showFieldErrors = List<bool>.filled(_Step.values.length, false);
  final Map<String, TextEditingController> _c = {};

  Map<String, dynamic> _meta = const {};
  List<dynamic> _quarters = const [];
  int _step = 0;
  bool _loadingMeta = false;
  bool _metaLoaded = false;
  bool _loadingQuarters = false;
  bool _resolvingGps = false;
  Future<bool>? _backgroundOvanieGpsFuture;
  bool _saving = false;
  bool _obscurePassword = true;
  bool _obscureConfirmation = true;

  String? _sellerType;
  String? _legalForm;
  String _logistics = 'ovanie';
  String _deliveryZone = 'abidjan';
  String _identityType = 'cni';
  String _identityMode = 'pdf';
  String _paymentMode = 'post_delivery';
  String? _operator;
  int? _communeId;
  int? _quarterId;
  String? _mainCategory;

  File? _selfie;
  File? _rccmFile;
  File? _taxFile;
  File? _identityPdf;
  File? _identityFront;
  File? _identityBack;
  String? _error;

  bool _acceptTerms = false;

  bool get _authenticated => VendorSession.instance.authenticated;
  _Step get _current => _Step.values[_step];

  TextEditingController ctrl(String key, [String initial = '']) {
    return _c.putIfAbsent(key, () => TextEditingController(text: initial));
  }

  @override
  void initState() {
    super.initState();
    final session = VendorSession.instance;
    if (session.authenticated && session.hasShop) {
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (!mounted) return;
        Navigator.of(context).pushReplacement(
          MaterialPageRoute(builder: (_) => const VendorShell()),
        );
      });
    }
    if (session.authenticated && !session.hasShop) {
      _step = session.onboardingStep.clamp(0, _lastStep).toInt();
    }
    if (widget.previewStep != null) {
      _step = widget.previewStep!.clamp(0, _lastStep).toInt();
    }
    final user = session.user ?? const <String, dynamic>{};
    ctrl('sellerName', '${user['name'] ?? ''}'.trim());
    ctrl('sellerEmail', '${user['email'] ?? ''}'.trim());
    ctrl('sellerPhone', _PhoneSpacingFormatter().formatEditUpdate(
      TextEditingValue.empty,
      TextEditingValue(text: _ciLocalPhone(user['phone'])),
    ).text);
    for (final key in const [
      'password',
      'password_confirmation',
      'companyName',
      'rccm',
      'taxpayerNumber',
      'shopName',
      'description',
      'commune',
      'district',
      'landmark',
      'address',
      'whatsapp',
      'business_email',
      'latitude',
      'longitude',
      'geo_accuracy',
      'identityNumber',
      'mmNumber',
      'mmHolder',
    ]) {
      ctrl(key);
    }
    ctrl('region', 'Abidjan');
    ctrl('city', 'Abidjan');
    WidgetsBinding.instance.addPostFrameCallback((_) {
      _ensureMetaLoaded();
      if (_current == _Step.shop && _logistics == 'ovanie') {
        _captureOvanieGpsInBackground();
      }
    });
  }

  @override
  void dispose() {
    _scrollController.dispose();
    for (final controller in _c.values) {
      controller.dispose();
    }
    super.dispose();
  }

  // ---------------------------------------------------------------------------
  // Données de référence (serveur)
  // ---------------------------------------------------------------------------

  Future<bool> _ensureMetaLoaded() async {
    if (_metaLoaded) return true;
    if (_loadingMeta) return false;
    setState(() {
      _loadingMeta = true;
      _error = null;
    });
    try {
      final data = await _repo.meta();
      if (!mounted) return false;
      setState(() {
        _meta = data;
        _metaLoaded = true;
      });
      return true;
    } catch (e) {
      if (mounted) setState(() => _error = ApiClient.friendlyError(e));
      return false;
    } finally {
      if (mounted) setState(() => _loadingMeta = false);
    }
  }

  /// Charge les références si besoin avant d'ouvrir une liste.
  Future<bool> _metaReady(bool Function() hasData, String what) async {
    if (hasData()) return true;
    if (_loadingMeta) {
      _snack('Chargement de la liste des $what…');
      return false;
    }
    final loaded = await _ensureMetaLoaded();
    if (!mounted) return false;
    if (!loaded || !hasData()) {
      _snack('Liste des $what indisponible. Vérifiez votre connexion puis réessayez.');
      return false;
    }
    return true;
  }

  void _snack(String message) {
    if (!mounted) return;
    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(SnackBar(content: Text(message)));
  }

  List<Map<String, dynamic>> get _communes => ((_meta['communes'] as List?) ?? const [])
      .whereType<Map>()
      .map((e) => Map<String, dynamic>.from(e))
      .toList();

  List<Map<String, dynamic>> get _categories =>
      (((_meta['shop_categories'] as List?) ?? (_meta['categories'] as List?) ?? const []))
          .whereType<Map>()
          .map((e) => Map<String, dynamic>.from(e))
          .toList();

  Map<String, String> _referenceMap(String key) {
    final raw = _meta[key];
    if (raw is! List) return const <String, String>{};
    final result = <String, String>{};
    for (final item in raw.whereType<Map>()) {
      final code = '${item['code'] ?? item['value'] ?? ''}'.trim();
      final label = '${item['label'] ?? code}'.trim();
      if (code.isNotEmpty && label.isNotEmpty) result[code] = label;
    }
    return result;
  }

  Map<String, String> get _sellerTypes => _referenceMap('seller_types');
  Map<String, String> get _legalForms => const {
        'sarl': 'SARL',
        'sarlu': 'SARLU',
        'sa': 'SA',
        'sas': 'SAS',
        'ei': 'Entreprise individuelle',
        'cooperative': 'Coopérative',
        'autre': 'Autre',
      };
  Map<String, String> get _deliveryZones => _referenceMap('delivery_zones');
  Map<String, String> get _identityTypes => _referenceMap('identity_types');
  Map<String, String> get _mobileMoneyOperators => _referenceMap('mobile_money_operators');

  // ---------------------------------------------------------------------------
  // Navigation
  // ---------------------------------------------------------------------------

  Future<void> _confirmLeave() async {
    final leave = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Quitter l’ouverture de boutique ?'),
        content: const Text('Vous pourrez reprendre plus tard à cette étape.'),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Continuer'),
          ),
          TextButton(
            onPressed: () => Navigator.pop(context, true),
            child: const Text('Quitter'),
          ),
        ],
      ),
    );
    if (leave == true && mounted) Navigator.of(context).pop();
  }

  void _goTo(int step) {
    setState(() {
      _step = step;
      _error = null;
    });
    if (_scrollController.hasClients) _scrollController.jumpTo(0);
    VendorSession.instance.saveOnboardingStep(_step);
  }

  void _back() {
    FocusManager.instance.primaryFocus?.unfocus();
    if (_saving) return;
    if (_step == 0) {
      _confirmLeave();
      return;
    }
    _goTo(_step - 1);
  }

  Future<void> _next() async {
    FocusManager.instance.primaryFocus?.unfocus();
    if (_saving || _loadingMeta || _resolvingGps) return;
    if (!_validateCurrentStep()) return;

    if (_current == _Step.shop && _logistics == 'ovanie') {
      final gpsReady = await _captureOvanieGpsInBackground(required: true, force: true);
      if (!gpsReady) return;
    }

    if (_step < _lastStep) {
      _goTo(_step + 1);
      return;
    }
    await _submit();
  }

  /// Validation de l'étape affichée : les erreurs de champ restent sous les
  /// champs (et disparaissent dès qu'ils sont corrigés) ; le bandeau d'erreur
  /// n'est utilisé que pour ce qui n'est pas un champ texte (sélection, fichier,
  /// case à cocher…), afin de ne jamais doubler le message en rouge.
  bool _validateCurrentStep() {
    final form = _forms[_step].currentState;
    final fieldsOk = form?.validate() ?? true;
    if (!fieldsOk) {
      setState(() {
        _showFieldErrors[_step] = true;
        _error = null;
      });
      _scrollToTop();
      return false;
    }
    final problem = _stepProblem(_current);
    setState(() => _error = problem);
    if (problem != null) {
      _scrollToBottom();
      return false;
    }
    return true;
  }

  /// Ramène le haut du formulaire à l'écran (la photo d'en-tête est au-dessus).
  void _scrollToTop() {
    final card = _cardKey.currentContext;
    if (card == null) return;
    Scrollable.ensureVisible(card, duration: const Duration(milliseconds: 220), curve: Curves.easeOut);
  }

  void _scrollToBottom() {
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (mounted && _scrollController.hasClients) {
        _scrollController.animateTo(
          _scrollController.position.maxScrollExtent,
          duration: const Duration(milliseconds: 250),
          curve: Curves.easeOut,
        );
      }
    });
  }

  static final _passportRegex = RegExp(r'^[A-Z][A-Z0-9]{6,11}$');
  static final _identityRegex = RegExp(r'^[A-Z0-9-]{6,25}$');

  /// Contrôle d'une étape à partir des données saisies (indépendant de
  /// l'affichage). Reprend les règles du serveur (ShopController).
  String? _stepProblem(_Step step) {
    String t(String key) => ctrl(key).text.trim();
    switch (step) {
      case _Step.personal:
        if (t('sellerName').isEmpty) return 'Le nom complet est obligatoire.';
        if (t('sellerEmail').isEmpty || !t('sellerEmail').contains('@')) {
          return 'Saisissez une adresse e-mail valide.';
        }
        if (_ciLocalPhone(t('sellerPhone')).length != 10) {
          return 'Saisissez un numéro ivoirien de 10 chiffres.';
        }
        if (!_authenticated) {
          if (ctrl('password').text.length < 8) {
            return 'Le mot de passe doit contenir au moins 8 caractères.';
          }
          if (ctrl('password').text != ctrl('password_confirmation').text) {
            return 'La confirmation du mot de passe ne correspond pas.';
          }
        }
        if (_sellerType == null) return 'Sélectionnez votre type de vendeur.';
        if (_sellerType == 'entreprise') {
          if (t('companyName').isEmpty ||
              _legalForm == null ||
              t('rccm').isEmpty ||
              t('taxpayerNumber').isEmpty) {
            return 'Complétez toutes les informations de l’entreprise.';
          }
          if (_rccmFile == null || _taxFile == null) {
            return 'Ajoutez le document RCCM et le document fiscal.';
          }
        }
        return null;
      case _Step.shop:
        if (t('shopName').isEmpty) return 'Le nom de la boutique est obligatoire.';
        if (_selfie == null) return 'Ajoutez une photo récente du responsable.';
        final descriptionLength = t('description').length;
        if (descriptionLength < 30 || descriptionLength > 700) {
          return 'La description doit contenir entre 30 et 700 caractères.';
        }
        if (_mainCategory == null || _mainCategory!.isEmpty) {
          return 'Sélectionnez la catégorie principale de la boutique.';
        }
        if (t('whatsapp').isNotEmpty && _ciLocalPhone(t('whatsapp')).length != 10) {
          return 'Le numéro WhatsApp professionnel doit contenir 10 chiffres.';
        }
        if (t('business_email').isNotEmpty && !t('business_email').contains('@')) {
          return 'L’adresse e-mail professionnelle est invalide.';
        }
        if (_communeId == null) return 'Sélectionnez la commune de la boutique.';
        if (t('district').isEmpty) return 'Renseignez le quartier de la boutique.';
        if (_quarters.isNotEmpty && _quarterId == null) {
          return 'Sélectionnez le quartier dans la liste proposée.';
        }
        if (t('landmark').isEmpty || _backgroundAddress().isEmpty) {
          return 'Le point de repère est obligatoire.';
        }
        return null;
      case _Step.identity:
        final number = t('identityNumber').toUpperCase();
        final validNumber = _identityType == 'passport'
            ? _passportRegex.hasMatch(number)
            : _identityRegex.hasMatch(number);
        if (!validNumber) return 'Vérifiez le numéro de la pièce.';
        if (_identityMode == 'pdf' && _identityPdf == null) {
          return 'Ajoutez le fichier PDF de votre pièce d’identité.';
        }
        if (_identityMode == 'scan' && (_identityFront == null || _identityBack == null)) {
          return 'Ajoutez le recto et le verso de votre pièce d’identité.';
        }
        return null;
      case _Step.payout:
        if (_operator == null) return 'Sélectionnez un opérateur de paiement.';
        if (_ciLocalPhone(t('mmNumber')).length != 10) {
          return 'Saisissez un numéro Mobile Money ivoirien de 10 chiffres.';
        }
        if (t('mmHolder').isEmpty) return 'Le nom du titulaire est obligatoire.';
        return null;
      case _Step.terms:
        if (!_acceptTerms) {
          return 'Acceptez les conditions vendeur pour ouvrir votre boutique.';
        }
        return null;
    }
  }

  Future<void> _submit() async {
    // Une étape reprise après une sauvegarde peut avoir des étapes précédentes
    // vides : on renvoie vers la première étape incomplète.
    for (final step in _Step.values) {
      final problem = _stepProblem(step);
      if (problem != null) {
        _goTo(step.index);
        setState(() => _error = problem);
        return;
      }
    }
    if (_logistics == 'ovanie' && !_hasOvanieGpsCoordinates) {
      final gpsReady = await _captureOvanieGpsInBackground(required: true);
      if (!gpsReady) return;
    }
    setState(() {
      _saving = true;
      _error = null;
    });
    try {
      final values = <String, dynamic>{
        'direct_payment': '0',
        'sellerName': ctrl('sellerName').text.trim(),
        'sellerEmail': ctrl('sellerEmail').text.trim(),
        'sellerPhone': ctrl('sellerPhone').text.trim(),
        'sellerType': _sellerType,
        if (_sellerType == 'entreprise') ...{
          'companyName': ctrl('companyName').text.trim(),
          'legalForm': _legalForm,
          'rccm': ctrl('rccm').text.trim().toUpperCase(),
          'taxpayerNumber': ctrl('taxpayerNumber').text.trim().toUpperCase(),
        },
        if (!_authenticated) ...{
          'password': ctrl('password').text,
          'password_confirmation': ctrl('password_confirmation').text,
          'device_name': 'OVANIE Vendeur Android',
        },
        'shopName': ctrl('shopName').text.trim(),
        'description': ctrl('description').text.trim(),
        'region': ctrl('region').text.trim().isEmpty ? 'Abidjan' : ctrl('region').text.trim(),
        'city': ctrl('city').text.trim().isEmpty ? 'Abidjan' : ctrl('city').text.trim(),
        'commune': ctrl('commune').text.trim(),
        'commune_id': _communeId,
        'district': ctrl('district').text.trim(),
        if (_quarterId != null) 'quarter_id': _quarterId,
        'landmark': ctrl('landmark').text.trim(),
        'address': _backgroundAddress(),
        if (ctrl('latitude').text.isNotEmpty) 'latitude': ctrl('latitude').text,
        if (ctrl('longitude').text.isNotEmpty) 'longitude': ctrl('longitude').text,
        if (ctrl('geo_accuracy').text.isNotEmpty) 'geo_accuracy': ctrl('geo_accuracy').text,
        if (ctrl('latitude').text.isNotEmpty && ctrl('longitude').text.isNotEmpty)
          'geo_source': 'device_gps',
        'main_category': _mainCategory,
        'delivery_zone': _deliveryZone,
        'logistics_type': _logistics,
        'whatsapp': ctrl('whatsapp').text.trim(),
        'business_email': ctrl('business_email').text.trim(),
        'identityCountry': 'ci',
        'identityType': _identityType,
        'identityNumber': ctrl('identityNumber').text.trim().toUpperCase(),
        'identityUploadMode': _identityMode,
        'payment_mode': _paymentMode,
        'mmOperator': _operator,
        'mmNumber': ctrl('mmNumber').text.trim(),
        'mmHolder': ctrl('mmHolder').text.trim(),
        'terms': _acceptTerms ? '1' : '0',
      };
      final response = await _repo.onboardShop(
        values,
        authenticated: _authenticated,
        selfie: _selfie,
        identityDocument: _identityMode == 'pdf' ? _identityPdf : null,
        identityFront: _identityMode == 'scan' ? _identityFront : null,
        identityBack: _identityMode == 'scan' ? _identityBack : null,
        rccmFile: _sellerType == 'entreprise' ? _rccmFile : null,
        taxFile: _sellerType == 'entreprise' ? _taxFile : null,
      );
      if (_authenticated) {
        await VendorSession.instance.refreshContext(strict: false);
        await VendorSession.instance.saveOnboardingStep(0);
      } else {
        await VendorSession.instance.acceptOnboarding(response);
      }
      if (!mounted) return;
      Navigator.of(context).pushAndRemoveUntil(
        MaterialPageRoute(builder: (_) => const VendorShell()),
        (_) => false,
      );
    } catch (e) {
      if (mounted) setState(() => _error = ApiClient.friendlyError(e));
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  // ---------------------------------------------------------------------------
  // Localisation
  // ---------------------------------------------------------------------------

  String _backgroundAddress() {
    final direct = ctrl('address').text.trim();
    if (direct.isNotEmpty) return direct;
    return [
      ctrl('landmark').text.trim(),
      ctrl('district').text.trim(),
      ctrl('commune').text.trim(),
      ctrl('city').text.trim(),
      ctrl('region').text.trim(),
    ].where((e) => e.isNotEmpty).toSet().join(', ');
  }

  String _normalizeLocation(Object? value) {
    var text = '${value ?? ''}'.trim().toLowerCase();
    const replacements = {
      'à': 'a',
      'á': 'a',
      'â': 'a',
      'ä': 'a',
      'ç': 'c',
      'è': 'e',
      'é': 'e',
      'ê': 'e',
      'ë': 'e',
      'î': 'i',
      'ï': 'i',
      'ô': 'o',
      'ö': 'o',
      'ù': 'u',
      'û': 'u',
      'ü': 'u',
    };
    replacements.forEach((from, to) => text = text.replaceAll(from, to));
    return text.replaceAll(RegExp(r'[^a-z0-9]+'), ' ').trim();
  }

  dynamic _findByName(List<dynamic> items, String name) {
    final target = _normalizeLocation(name);
    if (target.isEmpty) return null;
    for (final item in items) {
      if (item is! Map) continue;
      final candidate = _normalizeLocation(item['name']);
      if (candidate == target || candidate.contains(target) || target.contains(candidate)) {
        return item;
      }
    }
    return null;
  }

  Future<void> _selectCommune(dynamic raw) async {
    if (raw is! Map) return;
    final map = Map<String, dynamic>.from(raw);
    setState(() {
      _communeId = int.tryParse('${map['id']}');
      ctrl('commune').text = _cleanUiText(map['name']);
      _quarterId = null;
      ctrl('district').clear();
      _quarters = const [];
      _loadingQuarters = _communeId != null;
    });
    if (_communeId == null) return;
    try {
      final data = await _repo.quarters(_communeId!, communeName: ctrl('commune').text);
      if (mounted) setState(() => _quarters = data);
    } catch (e) {
      if (mounted) setState(() => _error = ApiClient.friendlyError(e));
    } finally {
      if (mounted) setState(() => _loadingQuarters = false);
    }
  }

  bool get _hasOvanieGpsCoordinates {
    final lat = double.tryParse(ctrl('latitude').text.trim());
    final lng = double.tryParse(ctrl('longitude').text.trim());
    return lat != null && lng != null;
  }

  /// Position GPS exigée par le serveur pour OVANIE Logistics.
  Future<bool> _captureOvanieGpsInBackground({
    bool required = false,
    bool force = false,
  }) async {
    if (_logistics != 'ovanie') return true;
    if (!force && _hasOvanieGpsCoordinates) return true;
    if (_backgroundOvanieGpsFuture != null) {
      return _backgroundOvanieGpsFuture!;
    }

    final future = () async {
      try {
        if (!await Geolocator.isLocationServiceEnabled()) {
          if (required && mounted) {
            setState(() => _error =
                'Activez la localisation de l’appareil pour enregistrer la position exacte de la boutique.');
          }
          return false;
        }

        var permission = await Geolocator.checkPermission();
        if (permission == LocationPermission.denied) {
          permission = await Geolocator.requestPermission();
        }
        if (permission == LocationPermission.denied ||
            permission == LocationPermission.deniedForever) {
          if (required && mounted) {
            setState(() => _error =
                'Autorisez la localisation pour qu’OVANIE Logistics enregistre la position GPS exacte de la boutique.');
          }
          return false;
        }

        final position = await Geolocator.getCurrentPosition(
          locationSettings: const LocationSettings(
            accuracy: LocationAccuracy.high,
            timeLimit: Duration(seconds: 20),
          ),
        );

        ctrl('latitude').text = position.latitude.toStringAsFixed(7);
        ctrl('longitude').text = position.longitude.toStringAsFixed(7);
        ctrl('geo_accuracy').text = position.accuracy.toStringAsFixed(1);
        return true;
      } catch (e) {
        if (required && mounted) {
          setState(() => _error = ApiClient.friendlyError(e));
        }
        return false;
      }
    }();

    _backgroundOvanieGpsFuture = future;
    try {
      return await future;
    } finally {
      _backgroundOvanieGpsFuture = null;
    }
  }

  /// Position du téléphone : mesure précise, sinon dernière position connue.
  Future<Position> _devicePosition() async {
    try {
      return await Geolocator.getCurrentPosition(
        locationSettings: const LocationSettings(
          accuracy: LocationAccuracy.high,
          timeLimit: Duration(seconds: 15),
        ),
      );
    } catch (_) {
      final last = await Geolocator.getLastKnownPosition();
      if (last != null) return last;
      throw const VendorApiException(
        'Position introuvable. Placez-vous près d’une fenêtre ou à l’extérieur, puis réessayez.',
      );
    }
  }

  /// Élément dont le nom est exactement celui recherché (accents et casse ignorés).
  Map<dynamic, dynamic>? _findExactByName(List<dynamic> items, String name) {
    final target = _normalizeLocation(name);
    if (target.isEmpty) return null;
    for (final item in items) {
      if (item is Map && _normalizeLocation(item['name']) == target) return item;
    }
    return null;
  }

  /// « Utiliser ma position » : remplit commune, quartier et repère.
  Future<void> _gps() async {
    if (_resolvingGps) return;
    setState(() {
      _resolvingGps = true;
      _error = null;
    });
    try {
      if (!await Geolocator.isLocationServiceEnabled()) {
        throw const VendorApiException('Activez la localisation de l’appareil puis réessayez.');
      }
      var permission = await Geolocator.checkPermission();
      if (permission == LocationPermission.denied) {
        permission = await Geolocator.requestPermission();
      }
      if (permission == LocationPermission.denied ||
          permission == LocationPermission.deniedForever) {
        throw const VendorApiException('Autorisez la localisation pour OVANIE Logistics.');
      }
      final position = await _devicePosition();
      await _ensureMetaLoaded();
      final resolved = await _repo.reverseShopLocation(position.latitude, position.longitude);
      final commune = '${resolved['commune'] ?? ''}'.trim();
      var district = '${resolved['district'] ?? ''}'.trim();
      final landmark = '${resolved['landmark'] ?? ''}'.trim();
      final address = '${resolved['address'] ?? resolved['display_name'] ?? ''}'.trim();

      dynamic matchedCommune = _findByName(_communes, commune);
      if (matchedCommune == null) {
        // Le serveur renvoie parfois un nom de quartier à la place de la
        // commune : on retrouve alors la commune qui contient ce quartier.
        final wanted = district.isNotEmpty ? district : commune;
        for (final candidate in _communes) {
          final quarter = _findExactByName((candidate['quarters'] as List?) ?? const [], wanted);
          if (quarter == null) continue;
          matchedCommune = candidate;
          district = '${quarter['name'] ?? wanted}'.trim();
          break;
        }
      }
      if (matchedCommune != null) {
        await _selectCommune(matchedCommune);
      } else {
        // Commune inconnue du catalogue : on ne garde pas un ancien choix.
        _communeId = null;
        _quarterId = null;
        _quarters = const [];
        ctrl('commune').clear();
        ctrl('district').clear();
      }
      ctrl('latitude').text = position.latitude.toStringAsFixed(7);
      ctrl('longitude').text = position.longitude.toStringAsFixed(7);
      ctrl('geo_accuracy').text = position.accuracy.toStringAsFixed(1);
      ctrl('region').text = 'Abidjan';
      ctrl('city').text = 'Abidjan';
      ctrl('address').text = address;
      if (matchedCommune != null && district.isNotEmpty) {
        final matchedQuarter = _findByName(_quarters, district);
        if (matchedQuarter is Map) {
          _quarterId = int.tryParse('${matchedQuarter['id']}');
          ctrl('district').text = _cleanUiText(matchedQuarter['name'] ?? district);
        } else if (_quarters.isEmpty) {
          ctrl('district').text = district;
        }
      }
      // Le repère suit toujours la position détectée.
      final place = ctrl('district').text.isNotEmpty ? ctrl('district').text : district;
      ctrl('landmark').text = landmark.isNotEmpty
          ? landmark
          : (place.isNotEmpty ? 'À proximité de $place' : 'Point GPS de la boutique');
      if (ctrl('address').text.isEmpty) {
        ctrl('address').text = _backgroundAddress();
      }
      if (!mounted) return;
      if (matchedCommune == null) {
        setState(() => _error =
            'Position trouvée, mais la commune n’a pas été reconnue. Sélectionnez la commune et le quartier dans les listes.');
        return;
      }
      if (ctrl('district').text.isEmpty) {
        setState(() => _error =
            'Commune détectée : ${ctrl('commune').text}. Le quartier n’a pas été reconnu, sélectionnez-le dans la liste.');
        return;
      }
      _snack('Localisation de la boutique détectée.');
      if (mounted) setState(() {});
    } catch (e) {
      if (mounted) setState(() => _error = ApiClient.friendlyError(e));
    } finally {
      if (mounted) setState(() => _resolvingGps = false);
    }
  }

  // ---------------------------------------------------------------------------
  // Pièces jointes
  // ---------------------------------------------------------------------------

  Future<ImageSource?> _askImageSource(String title) {
    return showWizardSelector<ImageSource>(
      context,
      title: title,
      items: const [ImageSource.camera, ImageSource.gallery],
      label: (s) => s == ImageSource.camera ? 'Prendre une photo' : 'Choisir dans la galerie',
      leading: (s) => Icon(
        s == ImageSource.camera ? Icons.photo_camera_outlined : Icons.photo_library_outlined,
        color: wizardNavy,
      ),
    );
  }

  Future<void> _pickSelfie() async {
    final source = await _askImageSource('Photo du responsable');
    if (source == null) return;
    final file = await ImagePicker().pickImage(
      source: source,
      imageQuality: 86,
      maxWidth: 1800,
      maxHeight: 1800,
    );
    if (file != null && mounted) {
      setState(() {
        _selfie = File(file.path);
        _error = null;
      });
    }
  }

  Future<void> _pickCompanyFile({required bool rccm}) async {
    final result = await FilePicker.pickFiles(
      type: FileType.custom,
      allowedExtensions: const ['pdf', 'jpg', 'jpeg', 'png'],
    );
    final path = result?.files.single.path;
    if (path == null || !mounted) return;
    setState(() {
      if (rccm) {
        _rccmFile = File(path);
      } else {
        _taxFile = File(path);
      }
      _error = null;
    });
  }

  Future<void> _pickIdentityPdf() async {
    final result = await FilePicker.pickFiles(
      type: FileType.custom,
      allowedExtensions: const ['pdf'],
    );
    final path = result?.files.single.path;
    if (path != null && mounted) {
      setState(() {
        _identityPdf = File(path);
        _error = null;
      });
    }
  }

  Future<void> _pickIdentitySide(bool front) async {
    final source = await _askImageSource(front ? 'Recto de la pièce' : 'Verso de la pièce');
    if (source == null) return;
    final file = await ImagePicker().pickImage(
      source: source,
      imageQuality: 88,
      maxWidth: 1800,
      maxHeight: 1800,
    );
    if (file == null || !mounted) return;
    setState(() {
      if (front) {
        _identityFront = File(file.path);
      } else {
        _identityBack = File(file.path);
      }
      _error = null;
    });
  }

  Future<void> _openVendorTerms() async {
    const channel = MethodChannel('ovanie/external_url');
    try {
      await channel.invokeMethod<bool>('openUrl', {
        'url': 'https://www.ovanie.com/conditions-vendeurs',
      });
    } catch (_) {
      _snack('Conditions vendeur : www.ovanie.com/conditions-vendeurs');
    }
  }

  // ---------------------------------------------------------------------------
  // Interface
  // ---------------------------------------------------------------------------

  /// Photo d'en-tête de chaque étape. Le logo, « Ouverture de boutique », le
  /// numéro d'étape et la flèche retour font partie de la photo ; `cardTop`
  /// est la position de la carte blanche, en proportion de la largeur.
  static const _headers = <({String name, double aspect, double cardTop})>[
    (name: 'step1', aspect: 863 / 577, cardTop: 533 / 863),
    (name: 'step2', aspect: 793 / 452, cardTop: 419 / 793),
    (name: 'step3', aspect: 879 / 528, cardTop: 488 / 879),
    (name: 'step4', aspect: 940 / 576, cardTop: 529 / 940),
    (name: 'step5', aspect: 887 / 583, cardTop: 536 / 887),
  ];

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    for (final header in _headers) {
      precacheImage(AssetImage(MkPhoto.asset(header.name)), context);
    }
  }

  @override
  Widget build(BuildContext context) {
    final busy = _saving || _loadingMeta || _resolvingGps;
    final padding = MediaQuery.paddingOf(context);
    final header = _headers[_step];

    return PopScope(
      canPop: false,
      onPopInvokedWithResult: (didPop, _) {
        if (!didPop) _back();
      },
      child: MkTheme(
        child: Scaffold(
          backgroundColor: Mk.page,
          body: LayoutBuilder(
            builder: (context, viewport) {
              final width = math.min(viewport.maxWidth, Mk.maxWidth);
              final side = width * .023;
              final cardTop = padding.top + width * header.cardTop;

              return SingleChildScrollView(
                controller: _scrollController,
                keyboardDismissBehavior: ScrollViewKeyboardDismissBehavior.onDrag,
                child: Center(
                  child: SizedBox(
                    width: width,
                    child: Stack(
                      children: <Widget>[
                        MkPhoto(
                          name: header.name,
                          width: width,
                          aspect: header.aspect,
                          label: 'OVANIE Vendeur. Ouverture de boutique, étape ${_step + 1} sur ${_Step.values.length}.',
                        ),
                        Padding(
                          padding: EdgeInsets.fromLTRB(side, cardTop, side, padding.bottom + 18),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.stretch,
                            children: <Widget>[
                              Container(
                                key: _cardKey,
                                padding: const EdgeInsets.fromLTRB(16, 18, 16, 16),
                                decoration: BoxDecoration(
                                  color: Colors.white,
                                  borderRadius: BorderRadius.circular(20),
                                  boxShadow: Mk.cardShadow,
                                ),
                                child: Form(
                                  key: _forms[_step],
                                  autovalidateMode: _showFieldErrors[_step]
                                      ? AutovalidateMode.onUserInteraction
                                      : AutovalidateMode.disabled,
                                  child: Column(
                                    crossAxisAlignment: CrossAxisAlignment.stretch,
                                    children: <Widget>[
                                      _stepHeading(),
                                      const SizedBox(height: 14),
                                      ..._stepFields(),
                                      if (_error != null) ...<Widget>[
                                        const SizedBox(height: 16),
                                        MkNotice(_error!),
                                      ],
                                      const SizedBox(height: 20),
                                      _actions(busy),
                                    ],
                                  ),
                                ),
                              ),
                              if (_current.footer != null) ...<Widget>[
                                const SizedBox(height: 16),
                                Padding(
                                  padding: const EdgeInsets.symmetric(horizontal: 12),
                                  child: MkSecureLine(_current.footer!),
                                ),
                              ],
                            ],
                          ),
                        ),
                        Positioned(
                          top: padding.top + width * .03,
                          left: 0,
                          child: MkPhotoBack(onTap: _back, size: math.max(48, width * .13)),
                        ),
                      ],
                    ),
                  ),
                ),
              );
            },
          ),
        ),
      ),
    );
  }

  Widget _stepHeading() {
    final step = _current;
    final texts = Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: <Widget>[
        Text(
          step.title,
          style: const TextStyle(color: Mk.navy, fontSize: 21, fontWeight: FontWeight.w800, height: 1.15, letterSpacing: -.5),
        ),
        const SizedBox(height: 5),
        Text(
          step.subtitle,
          style: const TextStyle(color: Mk.muted, fontSize: 12, height: 1.4, fontWeight: FontWeight.w500),
        ),
      ],
    );
    if (step.icon == null) return texts;
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: <Widget>[
        Container(
          width: 48,
          height: 48,
          decoration: const BoxDecoration(color: Mk.softBlue, shape: BoxShape.circle),
          child: Icon(step.icon, color: Mk.navy, size: 24),
        ),
        const SizedBox(width: 12),
        Expanded(child: texts),
      ],
    );
  }

  Widget _actions(bool busy) {
    final last = _step == _lastStep;
    final primary = MkButton(
      label: last ? 'Finaliser' : 'Suivant',
      loading: busy,
      onPressed: _next,
    );
    if (_step == 0) return primary;
    return Row(
      children: <Widget>[
        Expanded(child: MkOutlineButton(label: 'Précédent', onPressed: _saving ? null : _back)),
        const SizedBox(width: 12),
        Expanded(child: primary),
      ],
    );
  }

  List<Widget> _stepFields() {
    switch (_current) {
      case _Step.personal:
        return _personalStep();
      case _Step.shop:
        return _shopStep();
      case _Step.identity:
        return _identityStep();
      case _Step.payout:
        return _payoutStep();
      case _Step.terms:
        return _termsStep();
    }
  }

  static const _gap = SizedBox(height: 11);

  // ----- Étape 1 : informations personnelles --------------------------------

  List<Widget> _personalStep() {
    return <Widget>[
      _textField('sellerName', 'Nom complet', 'Ex. Jean Dupont', Icons.person_rounded, required: true),
      _gap,
      _textField('sellerEmail', 'Adresse email', 'Ex. jean.dupont@exemple.com', Icons.mail_rounded,
          required: true, keyboard: TextInputType.emailAddress, email: true),
      _gap,
      _phoneField('sellerPhone', 'Téléphone', Icons.phone_rounded, required: true),
      _gap,
      MkSelect(
        label: 'Type de vendeur',
        required: true,
        value: _sellerType == null ? null : _sellerTypes[_sellerType],
        hint: 'Sélectionnez votre type de vendeur',
        icon: Icons.work_rounded,
        onTap: () async {
          if (!await _metaReady(() => _sellerTypes.isNotEmpty, 'types de vendeur')) return;
          if (!mounted) return;
          final picked = await showWizardSelector<String>(
            context,
            title: 'Type de vendeur',
            selected: _sellerType,
            items: _sellerTypes.keys.toList(growable: false),
            label: (v) => _sellerTypes[v] ?? v,
          );
          if (picked != null) {
            setState(() {
              _sellerType = picked;
              _error = null;
            });
          }
        },
      ),
      if (_sellerType == 'entreprise') ...<Widget>[
        _gap,
        _textField('companyName', 'Raison sociale', 'Ex. Bâtir CI SARL', Icons.business_rounded, required: true),
        _gap,
        MkSelect(
          label: 'Forme juridique',
          required: true,
          value: _legalForm == null ? null : _legalForms[_legalForm],
          hint: 'Sélectionnez la forme juridique',
          icon: Icons.account_balance_rounded,
          onTap: () async {
            final picked = await showWizardSelector<String>(
              context,
              title: 'Forme juridique',
              items: _legalForms.keys.toList(growable: false),
              selected: _legalForm,
              label: (v) => _legalForms[v] ?? v,
            );
            if (picked != null) setState(() => _legalForm = picked);
          },
        ),
        _gap,
        _textField('rccm', 'Numéro RCCM', 'Ex. CI-ABJ-2026-B-00000', Icons.badge_rounded,
            required: true, upperCase: true),
        _gap,
        _textField('taxpayerNumber', 'Numéro contribuable', 'Compte contribuable', Icons.receipt_long_rounded,
            required: true, upperCase: true),
        _gap,
        const MkLabel('Documents de l’entreprise', required: true),
        _uploadRow(
          title: 'Document RCCM',
          hint: 'PDF, JPG ou PNG',
          file: _rccmFile,
          icon: Icons.description_rounded,
          onTap: () => _pickCompanyFile(rccm: true),
        ),
        const SizedBox(height: 10),
        _uploadRow(
          title: 'Document fiscal',
          hint: 'PDF, JPG ou PNG',
          file: _taxFile,
          icon: Icons.request_quote_rounded,
          onTap: () => _pickCompanyFile(rccm: false),
        ),
      ],
      if (!_authenticated) ...<Widget>[
        _gap,
        _passwordField('password', 'Mot de passe', 'Créez un mot de passe', true),
        _gap,
        _passwordField('password_confirmation', 'Confirmer le mot de passe', 'Confirmez votre mot de passe', false),
      ],
    ];
  }

  // ----- Étape 2 : informations de la boutique ------------------------------

  String? _categoryLabel() {
    if (_mainCategory == null || _mainCategory!.isEmpty) return null;
    for (final item in _categories) {
      if ('${item['slug'] ?? item['id']}' == _mainCategory) {
        return _cleanUiText(item['name'] ?? item['label'] ?? 'Catégorie');
      }
    }
    return null;
  }

  Widget _quarterField() {
    final communeSelected = _communeId != null;
    if (communeSelected && !_loadingQuarters && _quarters.isEmpty) {
      return _textField('district', 'Quartier', 'Ex. Cocody, Plateau, etc.', Icons.home_rounded, required: true);
    }
    return MkSelect(
      label: 'Quartier',
      required: true,
      value: ctrl('district').text.isEmpty ? null : ctrl('district').text,
      hint: !communeSelected
          ? 'Choisissez d’abord la commune'
          : (_loadingQuarters ? 'Chargement…' : 'Sélectionnez un quartier'),
      icon: Icons.home_rounded,
      // Le serveur exige un quartier connu de la commune (quarter_id).
      onTap: (!communeSelected || _loadingQuarters)
          ? null
          : () async {
              final picked = await showWizardSelector<dynamic>(
                context,
                title: 'Quartier',
                items: _quarters,
                searchable: true,
                label: (m) => _cleanUiText(m['name']),
              );
              if (picked != null) {
                setState(() {
                  ctrl('district').text = _cleanUiText(picked['name']);
                  _quarterId = int.tryParse('${picked['id']}');
                  _error = null;
                });
              }
            },
    );
  }

  List<Widget> _shopStep() {
    final selfieDone = _selfie != null;
    return <Widget>[
      _textField('shopName', 'Nom de la boutique', 'Ex. Dupont Matériaux BTP', Icons.storefront_rounded,
          required: true),
      _gap,
      const MkLabel('Photo du responsable', required: true),
      MkDashedBox(
        done: selfieDone,
        onTap: _pickSelfie,
        padding: const EdgeInsets.fromLTRB(14, 11, 14, 11),
        child: Row(
          children: <Widget>[
            Container(
              width: 44,
              height: 44,
              decoration: BoxDecoration(
                color: selfieDone ? const Color(0xFFD3EEE2) : const Color(0xFFE3ECFA),
                shape: BoxShape.circle,
              ),
              child: Icon(
                selfieDone ? Icons.check_rounded : Icons.person_rounded,
                color: selfieDone ? Mk.success : Mk.icon,
                size: 26,
              ),
            ),
            const SizedBox(width: 14),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: <Widget>[
                  Text(
                    selfieDone ? 'Photo ajoutée' : 'Photo du responsable',
                    style: const TextStyle(color: Mk.navy, fontSize: 14, fontWeight: FontWeight.w600),
                  ),
                  const SizedBox(height: 3),
                  Text(
                    selfieDone
                        ? '${_fileName(_selfie)} — toucher pour modifier'
                        : 'Photo KYC privée — JPG ou PNG, 5 Mo maximum — obligatoire',
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(color: Mk.muted, fontSize: 12, height: 1.35),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
      _gap,
      _descriptionField(),
      _gap,
      MkOutlineButton(
        label: _resolvingGps ? 'Localisation en cours…' : 'Utiliser ma position actuelle',
        icon: Icons.my_location_rounded,
        loading: _resolvingGps,
        height: 42,
        onPressed: _gps,
      ),
      _gap,
      Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Expanded(child: _textField('region', 'Région', 'Abidjan', Icons.map_rounded, required: true, readOnly: true)),
          const SizedBox(width: 12),
          Expanded(child: _textField('city', 'Ville', 'Abidjan', Icons.place_rounded, required: true, readOnly: true)),
        ],
      ),
      _gap,
      MkSelect(
        label: 'Commune',
        required: true,
        value: ctrl('commune').text.isEmpty ? null : ctrl('commune').text,
        hint: 'Sélectionnez une commune',
        icon: Icons.apartment_rounded,
        onTap: () async {
          if (!await _metaReady(() => _communes.isNotEmpty, 'communes')) return;
          if (!mounted) return;
          final picked = await showWizardSelector<Map<String, dynamic>>(
            context,
            title: 'Commune',
            items: _communes,
            searchable: true,
            label: (m) => _cleanUiText(m['name']),
          );
          if (picked != null) {
            _error = null;
            await _selectCommune(picked);
          }
        },
      ),
      _gap,
      _quarterField(),
      _gap,
      _textField('landmark', 'Point de repère', 'Ex. En face de la pharmacie, près du marché, etc.',
          Icons.place_rounded,
          required: true),
      _gap,
      MkSelect(
        label: 'Catégorie principale',
        required: true,
        value: _categoryLabel(),
        hint: 'Sélectionnez une catégorie',
        icon: Icons.grid_view_rounded,
        onTap: () async {
          if (!await _metaReady(() => _categories.isNotEmpty, 'catégories')) return;
          if (!mounted) return;
          final picked = await showWizardSelector<Map<String, dynamic>>(
            context,
            title: 'Catégorie principale',
            items: _categories,
            searchable: true,
            label: (m) => _cleanUiText(m['name'] ?? m['label'] ?? 'Catégorie'),
          );
          if (picked != null) {
            setState(() {
              _mainCategory = '${picked['slug'] ?? picked['id']}';
              _error = null;
            });
          }
        },
      ),
      _gap,
      MkSelect(
        label: 'Zone commerciale principale',
        required: true,
        value: _deliveryZones[_deliveryZone],
        hint: 'Sélectionnez une zone',
        icon: Icons.gps_fixed_rounded,
        onTap: () async {
          if (!await _metaReady(() => _deliveryZones.isNotEmpty, 'zones')) return;
          if (!mounted) return;
          final picked = await showWizardSelector<String>(
            context,
            title: 'Zone commerciale principale',
            selected: _deliveryZone,
            items: _deliveryZones.keys.toList(growable: false),
            label: (v) => _deliveryZones[v] ?? v,
          );
          if (picked != null) setState(() => _deliveryZone = picked);
        },
      ),
      _gap,
      _phoneField('whatsapp', 'WhatsApp professionnel', Icons.chat_rounded, required: false),
      _gap,
      _textField('business_email', 'Email professionnel', 'contact@votreboutique.ci', Icons.mail_rounded,
          keyboard: TextInputType.emailAddress, email: true),
      const SizedBox(height: 16),
      const Text(
        'Mode logistique de la boutique',
        style: TextStyle(color: Mk.navy, fontSize: 15, fontWeight: FontWeight.w800),
      ),
      const SizedBox(height: 10),
      MkChoiceRow(
        first: MkChoiceCard(
          selected: _logistics == 'ovanie',
          icon: Icons.local_shipping_rounded,
          title: 'OVANIE Logistics',
          subtitle: 'OVANIE organise la prise en charge des livraisons',
          onTap: () {
            setState(() {
              _logistics = 'ovanie';
              _error = null;
            });
            _captureOvanieGpsInBackground(force: true);
          },
        ),
        second: MkChoiceCard(
          selected: _logistics == 'seller',
          icon: Icons.warehouse_rounded,
          title: 'Logistique vendeur',
          subtitle: 'Vous organisez directement vos livraisons',
          onTap: () => setState(() {
            _logistics = 'seller';
            _error = null;
          }),
        ),
      ),
    ];
  }

  // ----- Étape 3 : vérification d'identité ----------------------------------

  List<Widget> _identityStep() {
    return <Widget>[
      const MkLabel('Pays de délivrance', required: true),
      TextFormField(
        initialValue: 'Côte d’Ivoire',
        readOnly: true,
        style: mkFieldText.copyWith(color: Mk.muted),
        decoration: mkInput(hint: 'Côte d’Ivoire', icon: Icons.map_rounded).copyWith(
          enabledBorder: OutlineInputBorder(
            borderRadius: BorderRadius.circular(12),
            borderSide: const BorderSide(color: Mk.border, width: 1.3),
          ),
          focusedBorder: OutlineInputBorder(
            borderRadius: BorderRadius.circular(12),
            borderSide: const BorderSide(color: Mk.border, width: 1.3),
          ),
        ),
      ),
      _gap,
      MkSelect(
        label: 'Type de pièce',
        required: true,
        value: _identityTypes[_identityType],
        hint: 'Sélectionnez le type de pièce',
        icon: Icons.description_rounded,
        onTap: () async {
          if (!await _metaReady(() => _identityTypes.isNotEmpty, 'pièces')) return;
          if (!mounted) return;
          final picked = await showWizardSelector<String>(
            context,
            title: 'Type de pièce',
            selected: _identityType,
            items: _identityTypes.keys.toList(growable: false),
            label: (v) => _identityTypes[v] ?? v,
          );
          if (picked != null && picked != _identityType) {
            setState(() => _identityType = picked);
            // Revalide le numéro déjà saisi avec la règle du nouveau type.
            if (ctrl('identityNumber').text.isNotEmpty) {
              WidgetsBinding.instance.addPostFrameCallback((_) {
                _forms[_step].currentState?.validate();
              });
            }
          }
        },
      ),
      _gap,
      _identityNumberField(),
      _gap,
      const MkLabel('Mode d’ajout du document', required: true),
      MkChoiceRow(
        first: MkChoiceCard(
          selected: _identityMode == 'pdf',
          icon: Icons.picture_as_pdf_rounded,
          title: 'Fichier PDF',
          subtitle: 'Un seul fichier (recto et verso)',
          onTap: () => setState(() {
            _identityMode = 'pdf';
            _error = null;
          }),
        ),
        second: MkChoiceCard(
          selected: _identityMode == 'scan',
          icon: Icons.file_copy_rounded,
          title: 'Recto / Verso',
          subtitle: 'Deux fichiers séparés (un pour chaque face)',
          onTap: () => setState(() {
            _identityMode = 'scan';
            _error = null;
          }),
        ),
      ),
      const SizedBox(height: 14),
      if (_identityMode == 'pdf')
        _uploadZone(
          title: 'Ajouter votre pièce d’identité',
          hint: 'Appuyez pour choisir un fichier • Format : PDF uniquement • Taille maximale : 5 Mo',
          file: _identityPdf,
          onTap: _pickIdentityPdf,
        )
      else ...<Widget>[
        _uploadRow(
          title: 'Recto de la pièce',
          hint: 'Photo JPG ou PNG, 5 Mo maximum',
          file: _identityFront,
          icon: Icons.credit_card_rounded,
          onTap: () => _pickIdentitySide(true),
        ),
        const SizedBox(height: 10),
        _uploadRow(
          title: 'Verso de la pièce',
          hint: 'Photo JPG ou PNG, 5 Mo maximum',
          file: _identityBack,
          icon: Icons.credit_card_rounded,
          onTap: () => _pickIdentitySide(false),
        ),
      ],
      const SizedBox(height: 14),
      MkInfoBox(
        icon: Icons.lightbulb_rounded,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: <Widget>[
            const Text(
              'Conseils pour un bon document',
              style: TextStyle(color: Mk.navy, fontSize: 14, fontWeight: FontWeight.w800),
            ),
            const SizedBox(height: 6),
            const _Bullet('Le document doit être clair, lisible et non expiré'),
            const _Bullet('Toutes les informations doivent être visibles'),
            _Bullet(_identityMode == 'pdf' ? 'Format accepté : PDF (max 5 Mo)' : 'Formats acceptés : JPG ou PNG (max 5 Mo)'),
          ],
        ),
      ),
    ];
  }

  // ----- Étape 4 : paiement vendeur -----------------------------------------

  List<Widget> _payoutStep() {
    const operatorAssets = <String, String>{
      'orange': 'assets/images/operators/orange.png',
      'mtn': 'assets/images/operators/mtn.png',
      'wave': 'assets/images/operators/wave.png',
      'moov': 'assets/images/operators/moov.png',
    };
    final operatorData = <String, (String, String)>{
      for (final entry in _mobileMoneyOperators.entries)
        if (operatorAssets.containsKey(entry.key)) entry.key: (entry.value, operatorAssets[entry.key]!),
    };
    Widget logo(String code, double size) => ClipRRect(
          borderRadius: BorderRadius.circular(8),
          child: Image.asset(operatorData[code]!.$2, width: size, height: size, fit: BoxFit.contain),
        );

    return <Widget>[
      const Text(
        'Reversements',
        style: TextStyle(color: Mk.navy, fontSize: 15, fontWeight: FontWeight.w800),
      ),
      const SizedBox(height: 10),
      MkChoiceRow(
        first: MkChoiceCard(
          selected: _paymentMode == 'post_delivery',
          icon: Icons.account_balance_wallet_rounded,
          title: 'Paiement après livraison 72h',
          subtitle: 'Recevez vos fonds 72h après confirmation de livraison',
          onTap: () => setState(() => _paymentMode = 'post_delivery'),
        ),
        second: MkChoiceCard(
          selected: _paymentMode == 'weekly',
          icon: Icons.calendar_month_rounded,
          title: 'Paiement hebdomadaire 7j',
          subtitle: 'Recevez vos reversements une fois par semaine',
          onTap: () => setState(() => _paymentMode = 'weekly'),
        ),
      ),
      _gap,
      MkSelect(
        label: 'Opérateur',
        required: true,
        value: _operator == null ? null : operatorData[_operator]?.$1,
        hint: 'Sélectionnez un opérateur',
        icon: Icons.phone_rounded,
        leading: _operator == null || !operatorData.containsKey(_operator) ? null : logo(_operator!, 28),
        onTap: () async {
          if (!await _metaReady(() => _mobileMoneyOperators.isNotEmpty, 'opérateurs')) return;
          if (!mounted) return;
          final picked = await showWizardSelector<String>(
            context,
            title: 'Opérateur Mobile Money',
            selected: _operator,
            items: operatorData.keys.toList(),
            label: (v) => operatorData[v]!.$1,
            leading: (v) => logo(v, 34),
          );
          if (picked != null) {
            setState(() {
              _operator = picked;
              _error = null;
            });
          }
        },
      ),
      _gap,
      _phoneField('mmNumber', 'Numéro de réception', Icons.smartphone_rounded, required: true),
      _gap,
      _textField('mmHolder', 'Nom du titulaire', 'Ex. Jean Dupont', Icons.person_rounded, required: true),
      _gap,
      const MkInfoBox(
        child: Text('Les informations de paiement sont utilisées uniquement pour vos reversements vendeur.'),
      ),
    ];
  }

  // ----- Étape 5 : conditions vendeur ---------------------------------------

  List<Widget> _termsStep() {
    return <Widget>[
      Container(
        padding: const EdgeInsets.fromLTRB(14, 14, 14, 10),
        decoration: BoxDecoration(color: Mk.softBlue, borderRadius: BorderRadius.circular(12)),
        child: const Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: <Widget>[
            Text(
              'Résumé des conditions',
              style: TextStyle(color: Mk.navy, fontSize: 15, fontWeight: FontWeight.w800),
            ),
            SizedBox(height: 8),
            _Bullet('Respect des règles de publication et de conformité des produits'),
            _Bullet('Exactitude des informations vendeur et des documents transmis'),
            _Bullet('Respect des délais de traitement, de livraison et du service client'),
            _Bullet('Acceptation des règles de commissions, reversements et litiges'),
          ],
        ),
      ),
      const SizedBox(height: 12),
      Container(
        padding: const EdgeInsets.fromLTRB(12, 12, 12, 4),
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(12),
          border: Border.all(color: Mk.border, width: 1.3),
        ),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: <Widget>[
            const Padding(
              padding: EdgeInsets.only(top: 1),
              child: Icon(Icons.description_rounded, color: Mk.icon, size: 24),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: <Widget>[
                  const Text(
                    'Conditions générales vendeur',
                    style: TextStyle(color: Mk.navy, fontSize: 14.5, fontWeight: FontWeight.w800),
                  ),
                  const SizedBox(height: 6),
                  const Text(
                    'Les présentes conditions générales définissent les règles et obligations applicables aux vendeurs sur la plateforme OVANIE, notamment en matière de publication, de vente, de commission, de paiement, de livraison et de service client.',
                    style: TextStyle(color: Mk.muted, fontSize: 12.5, height: 1.4, fontWeight: FontWeight.w500),
                  ),
                  TextButton(
                    onPressed: _openVendorTerms,
                    style: TextButton.styleFrom(
                      foregroundColor: Mk.blue,
                      padding: EdgeInsets.zero,
                      minimumSize: const Size(48, 48),
                      alignment: Alignment.centerLeft,
                      textStyle: const TextStyle(fontFamily: Mk.font, fontSize: 13.5, fontWeight: FontWeight.w700),
                    ),
                    child: const Row(
                      mainAxisSize: MainAxisSize.min,
                      children: <Widget>[
                        Flexible(child: Text('Lire les conditions complètes')),
                        SizedBox(width: 6),
                        Icon(Icons.arrow_forward_rounded, size: 18),
                      ],
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
      const SizedBox(height: 18),
      const Text(
        'Je confirme et j’accepte',
        style: TextStyle(color: Mk.navy, fontSize: 16, fontWeight: FontWeight.w800),
      ),
      const SizedBox(height: 10),
      Semantics(
        checked: _acceptTerms,
        child: Material(
          color: Mk.softOrange,
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(12),
            side: BorderSide(color: _acceptTerms ? Mk.orange : const Color(0xFFF6C7A6), width: 1.3),
          ),
          clipBehavior: Clip.antiAlias,
          child: InkWell(
            onTap: () => setState(() {
              _acceptTerms = !_acceptTerms;
              if (_acceptTerms) _error = null;
            }),
            child: Padding(
              padding: const EdgeInsets.fromLTRB(12, 14, 12, 14),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: <Widget>[
                  AnimatedContainer(
                    duration: const Duration(milliseconds: 140),
                    width: 24,
                    height: 24,
                    decoration: BoxDecoration(
                      color: _acceptTerms ? Mk.orange : Colors.white,
                      borderRadius: BorderRadius.circular(6),
                      border: Border.all(color: _acceptTerms ? Mk.orange : Mk.hint, width: 1.5),
                    ),
                    child: _acceptTerms ? const Icon(Icons.check_rounded, color: Colors.white, size: 18) : null,
                  ),
                  const SizedBox(width: 12),
                  const Expanded(
                    child: Text.rich(
                      TextSpan(
                        text: 'J’ai lu et j’accepte les Conditions Générales Vendeur OVANIE, ',
                        style: TextStyle(color: Mk.navy, fontSize: 13, fontWeight: FontWeight.w700, height: 1.4),
                        children: <InlineSpan>[
                          TextSpan(
                            text: 'y compris les règles de publication, de reversement, de livraison, de qualité et de gestion des litiges.',
                            style: TextStyle(color: Mk.muted, fontWeight: FontWeight.w500),
                          ),
                        ],
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
      const SizedBox(height: 12),
      const MkInfoBox(
        child: Text('Après validation, la boutique est créée avec les mêmes informations que sur le formulaire Web OVANIE.'),
      ),
    ];
  }

  // ---------------------------------------------------------------------------
  // Champs
  // ---------------------------------------------------------------------------

  Widget _textField(
    String key,
    String label,
    String hint,
    IconData icon, {
    bool required = false,
    bool email = false,
    bool upperCase = false,
    bool readOnly = false,
    TextInputType? keyboard,
  }) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: <Widget>[
        MkLabel(label, required: required),
        TextFormField(
          controller: ctrl(key),
          readOnly: readOnly,
          style: readOnly ? mkFieldText.copyWith(color: Mk.muted) : mkFieldText,
          keyboardType: keyboard,
          textInputAction: TextInputAction.next,
          textCapitalization: upperCase
              ? TextCapitalization.characters
              : (email ? TextCapitalization.none : TextCapitalization.sentences),
          decoration: mkInput(hint: hint, icon: icon, readOnly: readOnly),
          validator: (v) {
            final text = (v ?? '').trim();
            if (text.isEmpty) return required ? '$label : champ obligatoire.' : null;
            if (email && !text.contains('@')) return 'Adresse e-mail invalide.';
            return null;
          },
        ),
      ],
    );
  }

  Widget _descriptionField() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: <Widget>[
        const MkLabel('Description de l’activité', required: true),
        TextFormField(
          controller: ctrl('description'),
          style: mkFieldText,
          minLines: 2,
          maxLines: 5,
          // Le compteur est masqué ; la limite de 700 caractères reste appliquée.
          maxLength: 700,
          textCapitalization: TextCapitalization.sentences,
          decoration: mkInput(
            hint: 'Ex. Vente de matériaux de construction, quincaillerie, équipements BTP, etc.',
            icon: Icons.description_rounded,
          ).copyWith(counterText: '', hintMaxLines: 2),
          validator: (v) {
            final length = (v ?? '').trim().length;
            if (length == 0) return 'Décrivez votre activité.';
            if (length < 30) return 'Encore ${30 - length} caractère(s) minimum.';
            return null;
          },
        ),
      ],
    );
  }

  /// Numéro de la pièce : l'exemple reste une aide grise sous le champ ; le
  /// message rouge n'apparaît qu'après interaction ou tentative de validation
  /// et disparaît dès que la saisie devient correcte (pas de doublon dans le
  /// bandeau d'erreur). Même format que le serveur : lettres, chiffres et tiret,
  /// 6 à 25 caractères ; passeport : une lettre suivie de 6 à 11 lettres ou
  /// chiffres.
  Widget _identityNumberField() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: <Widget>[
        const MkLabel('Numéro de la pièce', required: true),
        TextFormField(
          controller: ctrl('identityNumber'),
          style: mkFieldText,
          autovalidateMode: AutovalidateMode.onUserInteraction,
          textCapitalization: TextCapitalization.characters,
          maxLength: 25,
          inputFormatters: <TextInputFormatter>[
            FilteringTextInputFormatter.allow(RegExp(r'[A-Za-z0-9-]')),
            TextInputFormatter.withFunction(
              (oldValue, newValue) => newValue.copyWith(text: newValue.text.toUpperCase()),
            ),
          ],
          onChanged: (_) {
            if (_error != null && _current == _Step.identity) setState(() => _error = null);
          },
          decoration: mkInput(
            hint: 'Saisissez le numéro de votre pièce',
            icon: Icons.badge_rounded,
          ).copyWith(
            counterText: '',
            helperText: _identityType == 'passport' ? 'Exemple : A1234567' : 'Exemple : CI1234567890',
          ),
          validator: (value) {
            final text = (value ?? '').trim().toUpperCase();
            if (text.isEmpty) return 'Saisissez le numéro de votre pièce.';
            final valid = _identityType == 'passport'
                ? _passportRegex.hasMatch(text)
                : _identityRegex.hasMatch(text);
            if (valid) return null;
            return _identityType == 'passport'
                ? 'Une lettre suivie de 6 à 11 lettres ou chiffres.'
                : '6 à 25 lettres ou chiffres, sans espace.';
          },
        ),
      ],
    );
  }

  /// Téléphone ivoirien : 10 chiffres, groupés par deux.
  Widget _phoneField(String key, String label, IconData icon, {required bool required}) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: <Widget>[
        MkLabel(label, required: required),
        TextFormField(
          controller: ctrl(key),
          style: mkFieldText,
          keyboardType: TextInputType.phone,
          textInputAction: TextInputAction.next,
          inputFormatters: <TextInputFormatter>[_PhoneSpacingFormatter()],
          decoration: mkInput(hint: '07 00 00 00 00', icon: icon),
          validator: (value) {
            final text = (value ?? '').trim();
            if (text.isEmpty) return required ? '$label : champ obligatoire.' : null;
            return _ciLocalPhone(text).length == 10 ? null : 'Numéro ivoirien de 10 chiffres.';
          },
        ),
      ],
    );
  }

  Widget _passwordField(String key, String label, String hint, bool first) {
    final obscure = first ? _obscurePassword : _obscureConfirmation;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: <Widget>[
        MkLabel(label, required: true),
        TextFormField(
          controller: ctrl(key),
          style: mkFieldText,
          obscureText: obscure,
          textInputAction: first ? TextInputAction.next : TextInputAction.done,
          decoration: mkInput(
            hint: hint,
            icon: Icons.lock_rounded,
            suffix: MkEye(
              hidden: obscure,
              onTap: () => setState(() {
                if (first) {
                  _obscurePassword = !_obscurePassword;
                } else {
                  _obscureConfirmation = !_obscureConfirmation;
                }
              }),
            ),
          ),
          validator: (v) {
            final value = v ?? '';
            if (value.isEmpty) return '$label : champ obligatoire.';
            if (first && value.length < 8) return '8 caractères minimum.';
            if (!first && value != ctrl('password').text) {
              return 'Les mots de passe ne correspondent pas.';
            }
            return null;
          },
        ),
      ],
    );
  }

  /// Grande zone d'ajout en pointillés (pièce d'identité en PDF).
  Widget _uploadZone({
    required String title,
    required String hint,
    required File? file,
    required VoidCallback onTap,
  }) {
    final done = file != null;
    return MkDashedBox(
      done: done,
      onTap: onTap,
      padding: const EdgeInsets.fromLTRB(14, 16, 14, 16),
      child: Column(
        children: <Widget>[
          Icon(
            done ? Icons.check_circle_rounded : Icons.cloud_upload_rounded,
            color: done ? Mk.success : Mk.icon,
            size: 34,
          ),
          const SizedBox(height: 6),
          Text(
            done ? 'Document ajouté' : title,
            textAlign: TextAlign.center,
            style: const TextStyle(color: Mk.navy, fontSize: 14.5, fontWeight: FontWeight.w700),
          ),
          const SizedBox(height: 4),
          Text(
            done ? '${_fileName(file)} — toucher pour modifier' : hint,
            textAlign: TextAlign.center,
            maxLines: 3,
            overflow: TextOverflow.ellipsis,
            style: const TextStyle(color: Mk.muted, fontSize: 12, height: 1.4),
          ),
        ],
      ),
    );
  }

  /// Ligne d'ajout en pointillés (recto, verso, documents d'entreprise).
  Widget _uploadRow({
    required String title,
    required String hint,
    required File? file,
    required IconData icon,
    required VoidCallback onTap,
  }) {
    final done = file != null;
    return MkDashedBox(
      done: done,
      onTap: onTap,
      padding: const EdgeInsets.fromLTRB(14, 12, 14, 12),
      child: Row(
        children: <Widget>[
          Icon(done ? Icons.check_circle_rounded : icon, color: done ? Mk.success : Mk.icon, size: 28),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                Text(title, style: const TextStyle(color: Mk.navy, fontSize: 14.5, fontWeight: FontWeight.w700)),
                const SizedBox(height: 2),
                Text(
                  done ? _fileName(file) : hint,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(color: Mk.muted, fontSize: 12),
                ),
              ],
            ),
          ),
          const SizedBox(width: 8),
          Text(
            done ? 'Modifier' : 'Ajouter',
            style: const TextStyle(color: Mk.blue, fontSize: 13.5, fontWeight: FontWeight.w700),
          ),
        ],
      ),
    );
  }
}

/// Ligne à puce bleue (résumé des conditions, conseils).
class _Bullet extends StatelessWidget {
  const _Bullet(this.text);

  final String text;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 5),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Container(
            width: 7,
            height: 7,
            margin: const EdgeInsets.only(top: 6, right: 12),
            decoration: const BoxDecoration(color: Mk.blue, shape: BoxShape.circle),
          ),
          Expanded(
            child: Text(
              text,
              style: const TextStyle(color: Mk.muted, fontSize: 12, height: 1.4, fontWeight: FontWeight.w500),
            ),
          ),
        ],
      ),
    );
  }
}

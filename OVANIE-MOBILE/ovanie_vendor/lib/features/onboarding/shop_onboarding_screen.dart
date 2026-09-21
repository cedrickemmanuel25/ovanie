import 'dart:io';

import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:geolocator/geolocator.dart';
import 'package:image_picker/image_picker.dart';

import '../../core/network/api_client.dart';
import '../../core/ui/vendor_design.dart';
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

class ShopOnboardingScreen extends StatefulWidget {
  const ShopOnboardingScreen({super.key});

  @override
  State<ShopOnboardingScreen> createState() => _ShopOnboardingScreenState();
}

class _ShopOnboardingScreenState extends State<ShopOnboardingScreen> {
  final _repo = VendorRepository.instance;
  final _scrollController = ScrollController();
  final _forms = List.generate(5, (_) => GlobalKey<FormState>());
  final Map<String, TextEditingController> _c = {};

  Map<String, dynamic> _meta = const {};
  List<dynamic> _quarters = const [];
  int _step = 0;
  bool _loadingMeta = false;
  bool _metaLoaded = false;
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
      _step = session.onboardingStep.clamp(0, 4).toInt();
    }
    final user = session.user ?? const <String, dynamic>{};
    ctrl('sellerName', '${user['name'] ?? ''}'.trim());
    ctrl('sellerEmail', '${user['email'] ?? ''}'.trim());
    ctrl('sellerPhone', _ciLocalPhone(user['phone']));
    ctrl('password');
    ctrl('password_confirmation');
    ctrl('companyName');
    ctrl('rccm');
    ctrl('taxpayerNumber');
    ctrl('shopName');
    ctrl('description');
    ctrl('region', 'Abidjan');
    ctrl('city', 'Abidjan');
    ctrl('commune');
    ctrl('district');
    ctrl('landmark');
    ctrl('address');
    ctrl('whatsapp');
    ctrl('business_email');
    ctrl('latitude');
    ctrl('longitude');
    ctrl('geo_accuracy');
    ctrl('identityNumber');
    ctrl('mmNumber');
    ctrl('mmHolder');
    WidgetsBinding.instance.addPostFrameCallback((_) {
      _ensureMetaLoaded();
      if (_step == 1 && _logistics == 'ovanie') {
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
      final categories =
          (data['shop_categories'] as List?) ??
          (data['categories'] as List?) ??
          const [];
      setState(() {
        _meta = data;
        _metaLoaded = true;
        if (_mainCategory == null &&
            categories.isNotEmpty &&
            categories.first is Map) {
          _mainCategory =
              '${(categories.first as Map)['slug'] ?? (categories.first as Map)['id'] ?? ''}';
        }
      });
      return true;
    } catch (e) {
      if (mounted) setState(() => _error = ApiClient.friendlyError(e));
      return false;
    } finally {
      if (mounted) setState(() => _loadingMeta = false);
    }
  }

  void _back() {
    FocusManager.instance.primaryFocus?.unfocus();
    if (_saving) return;
    if (_step == 0) {
      Navigator.maybePop(context);
      return;
    }
    setState(() {
      _step -= 1;
      _error = null;
    });
    VendorSession.instance.saveOnboardingStep(_step);
  }

  Future<void> _next() async {
    FocusManager.instance.primaryFocus?.unfocus();
    if (_saving || _loadingMeta || _resolvingGps) return;
    if (!_validateStep()) {
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (mounted && _scrollController.hasClients) {
          _scrollController.animateTo(
            _scrollController.position.maxScrollExtent,
            duration: const Duration(milliseconds: 250),
            curve: Curves.easeOut,
          );
        }
      });
      return;
    }
    if (_step == 1 && _logistics == 'ovanie') {
      final gpsReady = await _captureOvanieGpsInBackground(
        required: true,
        force: true,
      );
      if (!gpsReady) return;
    }

    if (_step < 4) {
      setState(() {
        _step += 1;
        _error = null;
      });
      _scrollController.jumpTo(0);
      await VendorSession.instance.saveOnboardingStep(_step);
      return;
    }
    await _submit();
  }

  bool _validateStep() {
    final form = _forms[_step].currentState;
    if (form == null || !form.validate()) {
      setState(
        () => _error = 'Complétez les champs obligatoires indiqués en rouge.',
      );
      return false;
    }
    if (_step == 0 && _sellerType == null) {
      setState(() => _error = 'Sélectionnez votre type de vendeur.');
      return false;
    }
    if (_step == 0 && _ciLocalPhone(ctrl('sellerPhone').text).length != 10) {
      setState(() => _error = 'Saisissez un numéro ivoirien de 10 chiffres.');
      return false;
    }
    if (_step == 0 && _sellerType == 'entreprise') {
      if (ctrl('companyName').text.trim().isEmpty ||
          _legalForm == null ||
          ctrl('rccm').text.trim().isEmpty ||
          ctrl('taxpayerNumber').text.trim().isEmpty) {
        setState(() => _error = 'Complétez toutes les informations de l’entreprise.');
        return false;
      }
      if (_rccmFile == null || _taxFile == null) {
        setState(() => _error = 'Ajoutez le document RCCM et le document fiscal.');
        return false;
      }
    }
    if (_step == 0 && !_authenticated) {
      if (ctrl('password').text.length < 8) {
        setState(
          () => _error = 'Le mot de passe doit contenir au moins 8 caractères.',
        );
        return false;
      }
      if (ctrl('password').text != ctrl('password_confirmation').text) {
        setState(
          () => _error = 'La confirmation du mot de passe ne correspond pas.',
        );
        return false;
      }
    }
    if (_step == 1) {
      if (_selfie == null) {
        setState(
          () => _error =
              'Ajoutez une photo récente du responsable.',
        );
        return false;
      }
      final descriptionLength = ctrl('description').text.trim().length;
      if (descriptionLength < 30 || descriptionLength > 700) {
        setState(() => _error = 'La description doit contenir entre 30 et 700 caractères.');
        return false;
      }
      final whatsappDigits = _ciLocalPhone(ctrl('whatsapp').text);
      if (ctrl('whatsapp').text.trim().isNotEmpty && whatsappDigits.length != 10) {
        setState(() => _error = 'Le numéro WhatsApp professionnel doit contenir 10 chiffres.');
        return false;
      }
      if (ctrl('business_email').text.trim().isNotEmpty &&
          !ctrl('business_email').text.contains('@')) {
        setState(() => _error = 'L’adresse e-mail professionnelle est invalide.');
        return false;
      }
      if (_mainCategory == null || _mainCategory!.isEmpty) {
        setState(
          () => _error = 'Sélectionnez la catégorie principale de la boutique.',
        );
        return false;
      }
      if (_communeId == null || ctrl('district').text.trim().isEmpty) {
        setState(
          () => _error =
              'La commune et le quartier de la boutique doivent être renseignés.',
        );
        return false;
      }
      if (ctrl('landmark').text.trim().isEmpty ||
          _backgroundAddress().isEmpty) {
        setState(
          () => _error =
              'Le point de repère et l’adresse de la boutique sont obligatoires.',
        );
        return false;
      }
    }
    if (_step == 2) {
      if (_identityMode == 'pdf' && _identityPdf == null) {
        setState(
          () => _error = 'Ajoutez le fichier PDF de votre pièce d’identité.',
        );
        return false;
      }
      if (_identityMode == 'scan' &&
          (_identityFront == null || _identityBack == null)) {
        setState(
          () => _error =
              'Ajoutez le recto et le verso de votre pièce d’identité.',
        );
        return false;
      }
    }
    if (_step == 3 && _operator == null) {
      setState(() => _error = 'Sélectionnez un opérateur de paiement.');
      return false;
    }
    if (_step == 3 && _ciLocalPhone(ctrl('mmNumber').text).length != 10) {
      setState(() => _error = 'Saisissez un numéro Mobile Money ivoirien de 10 chiffres.');
      return false;
    }
    if (_step == 4 && !_acceptTerms) {
      setState(() => _error = 'Vous devez accepter les conditions vendeur avant de finaliser.');
      return false;
    }
    setState(() => _error = null);
    return true;
  }

  Future<void> _submit() async {
    if (!_validateStep()) return;
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
        'region': ctrl('region').text.trim().isEmpty
            ? 'Abidjan'
            : ctrl('region').text.trim(),
        'city': ctrl('city').text.trim().isEmpty
            ? 'Abidjan'
            : ctrl('city').text.trim(),
        'commune': ctrl('commune').text.trim(),
        'commune_id': _communeId,
        'district': ctrl('district').text.trim(),
        if (_quarterId != null) 'quarter_id': _quarterId,
        'landmark': ctrl('landmark').text.trim(),
        'address': _backgroundAddress(),
        if (ctrl('latitude').text.isNotEmpty) 'latitude': ctrl('latitude').text,
        if (ctrl('longitude').text.isNotEmpty)
          'longitude': ctrl('longitude').text,
        if (ctrl('geo_accuracy').text.isNotEmpty)
          'geo_accuracy': ctrl('geo_accuracy').text,
        if (ctrl('latitude').text.isNotEmpty &&
            ctrl('longitude').text.isNotEmpty)
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
      if (candidate == target ||
          candidate.contains(target) ||
          target.contains(candidate)) {
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
    });
    if (_communeId == null) return;
    try {
      final data = await _repo.quarters(
        _communeId!,
        communeName: ctrl('commune').text,
      );
      if (mounted) setState(() => _quarters = data);
    } catch (e) {
      if (mounted) setState(() => _error = ApiClient.friendlyError(e));
    }
  }

  bool get _hasOvanieGpsCoordinates {
    final lat = double.tryParse(ctrl('latitude').text.trim());
    final lng = double.tryParse(ctrl('longitude').text.trim());
    return lat != null && lng != null;
  }

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

  Future<void> _gps({bool silentSuccess = false}) async {
    if (_resolvingGps) return;
    setState(() {
      _resolvingGps = true;
      _error = null;
    });
    try {
      if (!await Geolocator.isLocationServiceEnabled()) {
        throw const VendorApiException(
          'Activez la localisation de l’appareil puis réessayez.',
        );
      }
      var permission = await Geolocator.checkPermission();
      if (permission == LocationPermission.denied) {
        permission = await Geolocator.requestPermission();
      }
      if (permission == LocationPermission.denied ||
          permission == LocationPermission.deniedForever) {
        throw const VendorApiException(
          'Autorisez la localisation pour OVANIE Logistics.',
        );
      }
      final position = await Geolocator.getCurrentPosition(
        locationSettings: const LocationSettings(
          accuracy: LocationAccuracy.high,
          timeLimit: Duration(seconds: 20),
        ),
      );
      await _ensureMetaLoaded();
      final resolved = await _repo.reverseShopLocation(
        position.latitude,
        position.longitude,
      );
      final commune = '${resolved['commune'] ?? ''}'.trim();
      final district = '${resolved['district'] ?? ''}'.trim();
      final landmark = '${resolved['landmark'] ?? ''}'.trim();
      final address = '${resolved['address'] ?? resolved['display_name'] ?? ''}'
          .trim();
      ctrl('latitude').text = position.latitude.toStringAsFixed(7);
      ctrl('longitude').text = position.longitude.toStringAsFixed(7);
      ctrl('geo_accuracy').text = position.accuracy.toStringAsFixed(1);
      ctrl('region').text = 'Abidjan';
      ctrl('city').text = 'Abidjan';
      if (landmark.isNotEmpty) ctrl('landmark').text = landmark;
      if (address.isNotEmpty) ctrl('address').text = address;
      final communes = ((_meta['communes'] as List?) ?? const []);
      final matchedCommune = _findByName(communes, commune);
      if (matchedCommune != null) {
        await _selectCommune(matchedCommune);
      } else if (commune.isNotEmpty) {
        ctrl('commune').text = commune;
      }
      if (district.isNotEmpty) {
        final matchedQuarter = _findByName(_quarters, district);
        if (matchedQuarter is Map) {
          _quarterId = int.tryParse('${matchedQuarter['id']}');
          ctrl('district').text = _cleanUiText(
            matchedQuarter['name'] ?? district,
          );
        } else {
          ctrl('district').text = district;
        }
      }
      if (ctrl('landmark').text.isEmpty) {
        ctrl('landmark').text = district.isNotEmpty
            ? 'À proximité de $district'
            : 'Point GPS de la boutique';
      }
      if (ctrl('address').text.isEmpty) {
        ctrl('address').text = _backgroundAddress();
      }
      if (!silentSuccess && mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Localisation de la boutique détectée.'),
          ),
        );
      }
      if (mounted) setState(() {});
    } catch (e) {
      if (mounted) setState(() => _error = ApiClient.friendlyError(e));
    } finally {
      if (mounted) setState(() => _resolvingGps = false);
    }
  }

  Future<void> _pickSelfie() async {
    final source = await showWizardSelector<ImageSource>(
      context,
      title: 'Photo du responsable',
      items: const [ImageSource.gallery, ImageSource.camera],
      label: (s) =>
          s == ImageSource.camera ? 'Prendre une photo' : 'Choisir une photo',
    );
    if (source == null) return;
    final file = await ImagePicker().pickImage(
      source: source,
      imageQuality: 86,
      maxWidth: 1800,
      maxHeight: 1800,
    );
    if (file != null && mounted) setState(() => _selfie = File(file.path));
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
    });
  }

  Future<void> _openVendorTerms() async {
    const channel = MethodChannel('ovanie/external_url');
    try {
      await channel.invokeMethod<bool>('openUrl', {
        'url': 'https://www.ovanie.com/conditions-vendeurs',
      });
    } catch (_) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Conditions vendeur : www.ovanie.com/conditions-vendeurs')),
      );
    }
  }

  Future<void> _pickIdentityPdf() async {
    final result = await FilePicker.pickFiles(
      type: FileType.custom,
      allowedExtensions: const ['pdf'],
    );
    final path = result?.files.single.path;
    if (path != null && mounted) setState(() => _identityPdf = File(path));
  }

  Future<void> _pickIdentitySide(
    bool front, [
    ImageSource? requestedSource,
  ]) async {
    final source =
        requestedSource ??
        await showWizardSelector<ImageSource>(
          context,
          title: front ? 'Ajouter le recto' : 'Ajouter le verso',
          items: const [ImageSource.gallery, ImageSource.camera],
          label: (s) => s == ImageSource.camera
              ? 'Prendre une photo'
              : 'Importer une photo',
        );
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
    });
  }

  List<Map<String, dynamic>> get _communes =>
      ((_meta['communes'] as List?) ?? const [])
          .whereType<Map>()
          .map((e) => Map<String, dynamic>.from(e))
          .toList();
  List<Map<String, dynamic>> get _categories =>
      (((_meta['shop_categories'] as List?) ??
              (_meta['categories'] as List?) ??
              const []))
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

  Map<String, String> get _mobileMoneyOperators =>
      _referenceMap('mobile_money_operators');


  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF7FAFF),
      body: ListView(
        controller: _scrollController,
        padding: const EdgeInsets.only(bottom: 28),
        children: [
          VendorWizardHeader(
            currentStep: _step + 1,
            totalSteps: 5,
            onBack: _back,
          ),
          Transform.translate(
            offset: const Offset(0, -16),
            child: Form(
              key: _forms[_step],
              child: WizardCard(
                margin: const EdgeInsets.symmetric(horizontal: 12),
                padding: const EdgeInsets.all(16),
                radius: 20,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    _buildStep(),
                    if (_error != null) ...[
                      const SizedBox(height: 16),
                      VendorErrorBox(_error),
                    ],
                    const SizedBox(height: 20),
                    Row(
                      children: [
                        if (_step > 0) ...[
                          Expanded(
                            child: WizardSecondaryButton(
                              label: 'Précédent',
                              onPressed: _back,
                            ),
                          ),
                          const SizedBox(width: 12),
                        ],
                        Expanded(
                          child: WizardPrimaryButton(
                            label: _step == 4 ? 'Finaliser' : 'Suivant',
                            onPressed: _next,
                            loading: _saving || _loadingMeta || _resolvingGps,
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
            ),
          ),
          if (_step == 0 || _step == 1)
            Padding(
              padding: const EdgeInsets.fromLTRB(30, 0, 30, 18),
              child: Row(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  const Icon(
                    Icons.gpp_good_outlined,
                    color: Color(0xFF365785),
                    size: 22,
                  ),
                  const SizedBox(width: 8),
                  Flexible(
                    child: Text(
                      _step == 0
                          ? 'Les informations demandées sont sécurisées.'
                          : 'Les informations de votre boutique seront utilisées pour organiser votre activité et la logistique.',
                      textAlign: TextAlign.center,
                      style: const TextStyle(
                        color: wizardMuted,
                        fontSize: 12.5,
                      ),
                    ),
                  ),
                ],
              ),
            ),
        ],
      ),
    );
  }

  Widget _buildStep() {
    switch (_step) {
      case 0:
        return _personalStep();
      case 1:
        return _shopStep();
      case 2:
        return _identityStep();
      case 3:
        return _paymentStep();
      default:
        return _termsStep();
    }
  }

  Widget _personalStep() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        const Text(
          'Informations personnelles',
          textAlign: TextAlign.center,
          style: TextStyle(
            color: wizardText,
            fontSize: 26,
            fontWeight: FontWeight.w900,
          ),
        ),
        const SizedBox(height: 8),
        const Text(
          'Renseignez vos informations personnelles pour commencer la création de votre boutique.',
          style: TextStyle(color: wizardMuted, fontSize: 15, height: 1.35),
        ),
        const SizedBox(height: 28),
        _textField(
          'sellerName',
          'Nom complet',
          'Ex. Jean Dupont',
          Icons.person_rounded,
          required: true,
        ),
        const SizedBox(height: 16),
        _textField(
          'sellerEmail',
          'Adresse email',
          'Ex. jean.dupont@exemple.com',
          Icons.email_rounded,
          required: true,
          keyboard: TextInputType.emailAddress,
        ),
        const SizedBox(height: 16),
        _ciPhoneField(
          'sellerPhone',
          'Téléphone',
          required: true,
        ),
        const SizedBox(height: 16),
        WizardSelectField(
          label: 'Type de vendeur',
          value: _sellerType == null ? null : _sellerTypes[_sellerType],
          hint: 'Sélectionnez votre type de vendeur',
          icon: Icons.storefront_outlined,
          required: true,
          onTap: () async {
            final picked = await showWizardSelector<String>(
              context,
              title: 'Type de vendeur',
              items: _sellerTypes.keys.toList(growable: false),
              selected: _sellerType,
              label: (v) => _sellerTypes[v] ?? v,
            );
            if (picked != null) setState(() => _sellerType = picked);
          },
        ),
        if (!_authenticated) ...[
          const SizedBox(height: 16),
          _passwordField(
            'password',
            'Mot de passe',
            'Créez un mot de passe',
            true,
          ),
          const SizedBox(height: 16),
          _passwordField(
            'password_confirmation',
            'Confirmer le mot de passe',
            'Confirmez votre mot de passe',
            false,
          ),
        ],
        if (_sellerType == 'entreprise') ...[
          const SizedBox(height: 22),
          const WizardInfoBox(
            icon: Icons.business_rounded,
            child: Text(
              'Informations obligatoires pour une entreprise / société enregistrée.',
              style: TextStyle(color: wizardText, fontSize: 13.5, height: 1.35),
            ),
          ),
          const SizedBox(height: 16),
          _textField(
            'companyName',
            'Raison sociale',
            'Ex. Bâtir CI SARL',
            Icons.business_outlined,
            required: true,
          ),
          const SizedBox(height: 16),
          WizardSelectField(
            label: 'Forme juridique',
            value: _legalForm == null ? null : _legalForms[_legalForm],
            hint: 'Sélectionnez la forme juridique',
            icon: Icons.account_balance_outlined,
            required: true,
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
          const SizedBox(height: 16),
          _textField(
            'rccm',
            'Numéro RCCM',
            'Ex. CI-ABJ-2026-B-00000',
            Icons.badge_outlined,
            required: true,
          ),
          const SizedBox(height: 16),
          _textField(
            'taxpayerNumber',
            'Numéro contribuable',
            'Compte contribuable',
            Icons.receipt_long_outlined,
            required: true,
          ),
          const SizedBox(height: 16),
          _uploadBox(
            title: _rccmFile == null ? 'Document RCCM' : _rccmFile!.path.split(Platform.pathSeparator).last,
            subtitle: 'PDF, JPG ou PNG — obligatoire',
            icon: Icons.description_outlined,
            onTap: () => _pickCompanyFile(rccm: true),
          ),
          const SizedBox(height: 12),
          _uploadBox(
            title: _taxFile == null ? 'Document fiscal' : _taxFile!.path.split(Platform.pathSeparator).last,
            subtitle: 'PDF, JPG ou PNG — obligatoire',
            icon: Icons.request_quote_outlined,
            onTap: () => _pickCompanyFile(rccm: false),
          ),
        ],
      ],
    );
  }

  Widget _shopStep() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        const Text(
          'Informations de la boutique',
          textAlign: TextAlign.center,
          style: TextStyle(
            color: wizardText,
            fontSize: 26,
            fontWeight: FontWeight.w900,
          ),
        ),
        const SizedBox(height: 8),
        const Text(
          'Renseignez les informations principales de votre boutique pour continuer.',
          textAlign: TextAlign.center,
          style: TextStyle(color: wizardMuted, fontSize: 15, height: 1.35),
        ),
        const SizedBox(height: 24),
        _textField(
          'shopName',
          'Nom de la boutique',
          'Ex. Dupont Matériaux BTP',
          Icons.storefront_rounded,
          required: true,
        ),
        const SizedBox(height: 16),
        _uploadBox(
          title: _selfie == null
              ? 'Photo du responsable'
              : _selfie!.path.split(Platform.pathSeparator).last,
          subtitle: 'Photo KYC privée — JPG ou PNG, 5 Mo maximum — obligatoire',
          icon: Icons.person_rounded,
          onTap: _pickSelfie,
        ),
        const SizedBox(height: 16),
        _textField(
          'description',
          'Description de l’activité',
          'Ex. Vente de matériaux de construction, quincaillerie, équipements BTP, etc.',
          Icons.description_rounded,
          required: true,
          maxLines: 3,
          maxLength: 700,
          showCounter: true,
        ),
        Align(
          alignment: Alignment.centerLeft,
          child: TextButton.icon(
            onPressed: _resolvingGps ? null : () => _gps(),
            icon: const Icon(Icons.my_location, size: 18),
            label: const Text('Utiliser ma position actuelle'),
          ),
        ),
        if (_resolvingGps) ...[
          const SizedBox(height: 12),
          const LinearProgressIndicator(minHeight: 3),
        ],
        const SizedBox(height: 16),
        if (_resolvingGps)
          const Padding(
            padding: EdgeInsets.only(bottom: 10),
            child: Text(
              'Détection automatique de votre position en cours…',
              style: TextStyle(color: wizardMuted, fontSize: 12.5),
            ),
          ),
        _responsivePair(
          _textField(
            'region',
            'Région',
            'Abidjan',
            Icons.location_on_rounded,
            required: true,
            readOnly: true,
          ),
          _textField(
            'city',
            'Ville',
            'Abidjan',
            Icons.apartment_rounded,
            required: true,
            readOnly: true,
          ),
        ),
        const SizedBox(height: 14),
        _responsivePair(
          WizardSelectField(
            label: 'Commune',
            value: ctrl('commune').text.isEmpty ? null : ctrl('commune').text,
            hint: 'Sélectionnez une commune',
            icon: Icons.apartment_rounded,
            required: true,
            onTap: () async {
              final picked = await showWizardSelector<Map<String, dynamic>>(
                context,
                title: 'Commune',
                items: _communes,
                searchable: true,
                label: (m) => _cleanUiText(m['name']),
              );
              if (picked != null) await _selectCommune(picked);
            },
          ),
          _textField(
            'district',
            'Quartier',
            'Ex. Cocody, Plateau, etc.',
            Icons.location_on_outlined,
            required: true,
          ),
        ),
        const SizedBox(height: 14),
        _textField(
          'landmark',
          'Point de repère',
          'Ex. En face de la pharmacie, près du marché, etc.',
          Icons.signpost_outlined,
          required: true,
        ),
        const SizedBox(height: 14),
        Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Expanded(
              child: WizardSelectField(
                label: 'Catégorie principale',
                value: _categoryLabel(),
                hint: 'Sélectionnez une catégorie',
                icon: Icons.grid_view_rounded,
                required: true,
                onTap: () async {
                  final picked = await showWizardSelector<Map<String, dynamic>>(
                    context,
                    title: 'Catégorie principale',
                    items: _categories,
                    searchable: true,
                    label: (m) =>
                        _cleanUiText(m['name'] ?? m['label'] ?? 'Catégorie'),
                  );
                  if (picked != null) {
                    setState(
                      () => _mainCategory = '${picked['slug'] ?? picked['id']}',
                    );
                  }
                },
              ),
            ),
            const SizedBox(width: 14),
            Expanded(
              child: WizardSelectField(
                label: 'Zone commerciale principale',
                value: _deliveryZones[_deliveryZone],
                hint: 'Sélectionnez une zone',
                icon: Icons.storefront_rounded,
                required: true,
                onTap: () async {
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
            ),
          ],
        ),
        const SizedBox(height: 16),
        _responsivePair(
          _ciPhoneField(
            'whatsapp',
            'WhatsApp professionnel',
            required: false,
          ),
          _textField(
            'business_email',
            'Email professionnel',
            'contact@votreboutique.ci',
            Icons.email_outlined,
            keyboard: TextInputType.emailAddress,
          ),
        ),
        const SizedBox(height: 18),
        const WizardFieldLabel(
          'Mode logistique de la boutique',
          required: true,
        ),
        Row(
          children: [
            Expanded(
              child: WizardChoiceCard(
                selected: _logistics == 'ovanie',
                title: 'OVANIE Logistics',
                subtitle: 'OVANIE organise la prise en charge des livraisons',
                icon: Icons.local_shipping_rounded,
                onTap: () {
                  setState(() => _logistics = 'ovanie');
                  _captureOvanieGpsInBackground(force: true);
                },
              ),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: WizardChoiceCard(
                selected: _logistics == 'seller',
                title: 'Logistique vendeur',
                subtitle: 'Vous organisez directement vos livraisons',
                icon: Icons.warehouse_rounded,
                orange: false,
                onTap: () => setState(() => _logistics = 'seller'),
              ),
            ),
          ],
        ),
      ],
    );
  }

  Widget _identityStep() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        const WizardIntro(
          icon: Icons.badge_outlined,
          title: 'Vérification d’identité',
          subtitle:
              'Ces informations nous permettent de vérifier votre identité et de sécuriser votre compte vendeur.',
        ),
        const SizedBox(height: 28),
        WizardSelectField(
          label: 'Pays de délivrance',
          value: '🇨🇮  Côte d’Ivoire',
          hint: 'Côte d’Ivoire',
          icon: Icons.public_rounded,
          required: true,
          onTap: () {},
        ),
        const SizedBox(height: 16),
        WizardSelectField(
          label: 'Type de pièce',
          value: _identityTypes[_identityType],
          hint: 'Sélectionnez le type de pièce',
          icon: Icons.badge_outlined,
          required: true,
          onTap: () async {
            final picked = await showWizardSelector<String>(
              context,
              title: 'Type de pièce',
              selected: _identityType,
              items: _identityTypes.keys.toList(growable: false),
              label: (v) => _identityTypes[v] ?? v,
            );
            if (picked != null) setState(() => _identityType = picked);
          },
        ),
        const SizedBox(height: 16),
        _textField(
          'identityNumber',
          'Numéro de la pièce',
          'Saisissez le numéro de votre pièce',
          Icons.tag_rounded,
          required: true,
        ),
        const Padding(
          padding: EdgeInsets.fromLTRB(2, 6, 2, 0),
          child: Text(
            'Exemple : CI1234567890',
            style: TextStyle(color: wizardMuted, fontSize: 12.5),
          ),
        ),
        const SizedBox(height: 18),
        const WizardFieldLabel('Mode d’ajout du document', required: true),
        Row(
          children: [
            Expanded(
              child: WizardChoiceCard(
                selected: _identityMode == 'pdf',
                title: 'Fichier PDF',
                subtitle: 'Un seul fichier\n(recto et verso)',
                icon: Icons.description_rounded,
                onTap: () => setState(() => _identityMode = 'pdf'),
              ),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: WizardChoiceCard(
                selected: _identityMode == 'scan',
                title: 'Recto / Verso',
                subtitle: 'Deux fichiers séparés\n(un pour chaque face)',
                icon: Icons.image_outlined,
                orange: false,
                onTap: () => setState(() => _identityMode = 'scan'),
              ),
            ),
          ],
        ),
        const SizedBox(height: 16),
        if (_identityMode == 'pdf')
          _uploadBox(
            title: _identityPdf == null
                ? 'Ajouter votre pièce d’identité'
                : 'PDF sélectionné',
            subtitle: _identityPdf == null
                ? 'Glissez-déposez un fichier ici ou appuyez pour choisir\nFormat : PDF uniquement • Taille maximale : 5 Mo'
                : _identityPdf!.path.split(Platform.pathSeparator).last,
            icon: Icons.cloud_upload_outlined,
            onTap: _pickIdentityPdf,
          )
        else
          Row(
            children: [
              Expanded(child: _identitySideCard(front: true)),
              const SizedBox(width: 12),
              Expanded(child: _identitySideCard(front: false)),
            ],
          ),
        const SizedBox(height: 16),
        const WizardInfoBox(
          child: Text(
            'Conseils pour un bon document\n• Le document doit être clair, lisible et non expiré\n• Toutes les informations doivent être visibles\n• Format accepté : PDF (max 5 Mo)',
            style: TextStyle(color: wizardText, fontSize: 13.5, height: 1.45),
          ),
        ),
      ],
    );
  }

  Widget _paymentStep() {
    const operatorAssets = <String, String>{
      'orange': 'assets/images/operators/orange.png',
      'mtn': 'assets/images/operators/mtn.png',
      'wave': 'assets/images/operators/wave.png',
      'moov': 'assets/images/operators/moov.png',
    };
    final operatorData = <String, (String, String)>{
      for (final entry in _mobileMoneyOperators.entries)
        if (operatorAssets.containsKey(entry.key))
          entry.key: (entry.value, operatorAssets[entry.key]!),
    };
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        const WizardIntro(
          icon: Icons.account_balance_wallet_outlined,
          title: 'Paiement vendeur',
          subtitle:
              'Choisissez votre mode de reversement et renseignez les informations de paiement pour recevoir vos paiements vendeur.',
        ),
        const SizedBox(height: 26),
        const Text(
          'Reversements',
          style: TextStyle(
            color: wizardText,
            fontSize: 20,
            fontWeight: FontWeight.w900,
          ),
        ),
        const SizedBox(height: 12),
        Row(
          children: [
            Expanded(
              child: WizardChoiceCard(
                selected: _paymentMode == 'post_delivery',
                title: 'Paiement après\nlivraison 72h',
                subtitle:
                    'Recevez vos fonds 72h après confirmation de livraison',
                icon: Icons.account_balance_wallet_rounded,
                onTap: () => setState(() => _paymentMode = 'post_delivery'),
              ),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: WizardChoiceCard(
                selected: _paymentMode == 'weekly',
                title: 'Paiement hebdomadaire 7j',
                subtitle: 'Recevez vos reversements une fois par semaine',
                icon: Icons.calendar_month_rounded,
                orange: false,
                onTap: () => setState(() => _paymentMode = 'weekly'),
              ),
            ),
          ],
        ),
        const SizedBox(height: 18),
        WizardSelectField(
          label: 'Opérateur',
          value: _operator == null ? null : operatorData[_operator]!.$1,
          hint: 'Sélectionnez un opérateur',
          icon: Icons.phone_android_rounded,
          required: true,
          leading: _operator == null
              ? null
              : ClipRRect(
                  borderRadius: BorderRadius.circular(8),
                  child: Image.asset(
                    operatorData[_operator]!.$2,
                    width: 34,
                    height: 34,
                    fit: BoxFit.contain,
                  ),
                ),
          onTap: () async {
            final picked = await showWizardSelector<String>(
              context,
              title: 'Opérateur',
              selected: _operator,
              items: operatorData.keys.toList(),
              label: (v) => operatorData[v]!.$1,
              leading: (v) => ClipRRect(
                borderRadius: BorderRadius.circular(8),
                child: Image.asset(
                  operatorData[v]!.$2,
                  width: 36,
                  height: 36,
                  fit: BoxFit.contain,
                ),
              ),
            );
            if (picked != null) setState(() => _operator = picked);
          },
        ),
        const SizedBox(height: 16),
        _ciPhoneField(
          'mmNumber',
          'Numéro de réception',
          required: true,
        ),
        const SizedBox(height: 16),
        _textField(
          'mmHolder',
          'Nom du titulaire',
          'Ex. Jean Dupont',
          Icons.person_rounded,
          required: true,
        ),
        const SizedBox(height: 20),
        const WizardInfoBox(
          icon: Icons.verified_user_rounded,
          child: Text(
            'Les informations de paiement sont utilisées uniquement pour vos reversements vendeur.',
            style: TextStyle(color: wizardText, fontSize: 14, height: 1.35),
          ),
        ),
      ],
    );
  }

  Widget _termsStep() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        const WizardIntro(
          icon: Icons.fact_check_outlined,
          title: 'Conditions vendeur',
          subtitle:
              'Veuillez lire et accepter les conditions pour finaliser l’ouverture de votre boutique.',
        ),
        const SizedBox(height: 22),
        Container(
          padding: const EdgeInsets.all(18),
          decoration: BoxDecoration(
            color: const Color(0xFFF5FAFF),
            borderRadius: BorderRadius.circular(12),
            border: Border.all(color: const Color(0xFFCEE3FF)),
          ),
          child: const Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                'Résumé des conditions',
                style: TextStyle(
                  color: wizardText,
                  fontSize: 17,
                  fontWeight: FontWeight.w900,
                ),
              ),
              SizedBox(height: 8),
              Text(
                '• Respect des règles de publication et de conformité des produits\n• Exactitude des informations vendeur et des documents transmis\n• Respect des délais de traitement, de livraison et du service client\n• Acceptation des règles de commissions, reversements et litiges',
                style: TextStyle(
                  color: wizardMuted,
                  fontSize: 13.5,
                  height: 1.45,
                ),
              ),
            ],
          ),
        ),
        const SizedBox(height: 14),
        Container(
          padding: const EdgeInsets.all(18),
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(12),
            border: Border.all(color: wizardBorder),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Text(
                'Conditions générales vendeur',
                style: TextStyle(
                  color: wizardText,
                  fontSize: 17,
                  fontWeight: FontWeight.w900,
                ),
              ),
              const SizedBox(height: 6),
              const Text(
                'Les présentes conditions générales définissent les règles et obligations applicables aux vendeurs sur la plateforme OVANIE, notamment en matière de publication, de vente, de commission, de paiement, de livraison et de service client.',
                style: TextStyle(
                  color: wizardMuted,
                  fontSize: 13.5,
                  height: 1.45,
                ),
              ),
              const SizedBox(height: 12),
              SizedBox(
                width: double.infinity,
                child: OutlinedButton.icon(
                  onPressed: _openVendorTerms,
                  icon: const Icon(Icons.link_rounded),
                  label: const Text('Lire les conditions complètes'),
                ),
              ),
            ],
          ),
        ),
        const SizedBox(height: 18),
        const Text(
          'Je confirme et j’accepte',
          style: TextStyle(
            color: wizardText,
            fontSize: 18,
            fontWeight: FontWeight.w900,
          ),
        ),
        const SizedBox(height: 8),
        _conditionTile(
          'J’ai lu et j’accepte les Conditions Générales Vendeur OVANIE, y compris les règles de publication, de reversement, de livraison, de qualité et de gestion des litiges.',
          _acceptTerms,
          (v) => setState(() => _acceptTerms = v),
        ),
        const SizedBox(height: 14),
        const WizardInfoBox(
          icon: Icons.verified_user_rounded,
          child: Text(
            'Après validation, la boutique est créée avec les mêmes informations que sur le formulaire Web OVANIE.',
            style: TextStyle(color: wizardText, fontSize: 14, height: 1.35),
          ),
        ),
      ],
    );
  }

  Widget _conditionTile(String text, bool value, ValueChanged<bool> onChanged) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 9),
      child: InkWell(
        onTap: () => onChanged(!value),
        borderRadius: BorderRadius.circular(10),
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(10),
            border: Border.all(color: wizardBorder),
          ),
          child: Row(
            children: [
              Checkbox(
                value: value,
                onChanged: (v) => onChanged(v ?? false),
                activeColor: wizardOrange,
              ),
              const SizedBox(width: 4),
              Expanded(
                child: Text(
                  text,
                  style: const TextStyle(
                    color: wizardMuted,
                    fontSize: 13.5,
                    height: 1.3,
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _identitySideCard({required bool front}) {
    final file = front ? _identityFront : _identityBack;
    return Container(
      constraints: const BoxConstraints(minHeight: 194),
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: const Color(0xFFF9FBFE),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: const Color(0xFFBFD3EF)),
      ),
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          if (file == null)
            const Icon(
              Icons.add_photo_alternate_outlined,
              color: wizardBlue,
              size: 42,
            )
          else
            ClipRRect(
              borderRadius: BorderRadius.circular(8),
              child: Image.file(
                file,
                height: 78,
                width: double.infinity,
                fit: BoxFit.cover,
              ),
            ),
          const SizedBox(height: 8),
          Text(
            front ? 'Recto' : 'Verso',
            style: const TextStyle(
              color: wizardText,
              fontSize: 16,
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 10),
          Row(
            children: [
              Expanded(
                child: OutlinedButton.icon(
                  onPressed: () =>
                      _pickIdentitySide(front, ImageSource.gallery),
                  icon: const Icon(Icons.file_upload_outlined, size: 18),
                  label: const Text('Importer', maxLines: 1),
                ),
              ),
              const SizedBox(width: 8),
              Expanded(
                child: OutlinedButton.icon(
                  onPressed: () => _pickIdentitySide(front, ImageSource.camera),
                  icon: const Icon(Icons.photo_camera_outlined, size: 18),
                  label: const Text('Prendre', maxLines: 1),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _uploadBox({
    required String title,
    required String subtitle,
    required IconData icon,
    required VoidCallback onTap,
  }) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(12),
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 22),
        decoration: BoxDecoration(
          color: const Color(0xFFFBFDFF),
          borderRadius: BorderRadius.circular(12),
          border: Border.all(color: const Color(0xFF9EC7FF), width: 1.2),
        ),
        child: Row(
          children: [
            Icon(icon, color: const Color(0xFF1678E8), size: 48),
            const SizedBox(width: 18),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    title,
                    style: const TextStyle(
                      color: wizardText,
                      fontSize: 16,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    subtitle,
                    style: const TextStyle(
                      color: wizardMuted,
                      fontSize: 12.5,
                      height: 1.35,
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _textField(
    String key,
    String label,
    String hint,
    IconData icon, {
    bool required = false,
    TextInputType? keyboard,
    int maxLines = 1,
    int? maxLength,
    bool readOnly = false,
    bool showCounter = false,
    List<TextInputFormatter>? inputFormatters,
  }) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        WizardFieldLabel(label, required: required),
        TextFormField(
          controller: ctrl(key),
          keyboardType: keyboard,
          maxLines: maxLines,
          maxLength: maxLength,
          readOnly: readOnly,
          inputFormatters: inputFormatters,
          decoration: wizardInput(
            hint: hint,
            icon: icon,
            readOnly: readOnly,
          ).copyWith(counterText: showCounter ? null : ''),
          validator: required
              ? (v) =>
                    (v ?? '').trim().isEmpty ? '$label est obligatoire.' : null
              : null,
        ),
      ],
    );
  }

  Widget _ciPhoneField(
    String key,
    String label, {
    required bool required,
  }) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        WizardFieldLabel(label, required: required),
        TextFormField(
          controller: ctrl(key),
          keyboardType: TextInputType.phone,
          inputFormatters: [_PhoneSpacingFormatter()],
          decoration: wizardInput(
            hint: '07 00 00 00 00',
            icon: Icons.phone_rounded,
          ).copyWith(
            prefixText: '+225  ',
            prefixStyle: const TextStyle(
              color: wizardText,
              fontWeight: FontWeight.w800,
            ),
          ),
          validator: (value) {
            final text = (value ?? '').trim();
            if (text.isEmpty) {
              return required ? '$label est obligatoire.' : null;
            }
            return _ciLocalPhone(text).length == 10
                ? null
                : 'Saisissez un numéro ivoirien de 10 chiffres.';
          },
        ),
      ],
    );
  }

  Widget _passwordField(String key, String label, String hint, bool first) {
    final obscure = first ? _obscurePassword : _obscureConfirmation;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        WizardFieldLabel(label, required: true),
        TextFormField(
          controller: ctrl(key),
          obscureText: obscure,
          decoration: wizardInput(
            hint: hint,
            icon: Icons.lock_rounded,
            suffix: IconButton(
              onPressed: () => setState(() {
                if (first) {
                  _obscurePassword = !_obscurePassword;
                } else {
                  _obscureConfirmation = !_obscureConfirmation;
                }
              }),
              icon: Icon(
                obscure
                    ? Icons.visibility_off_outlined
                    : Icons.visibility_outlined,
                color: const Color(0xFF60708B),
              ),
            ),
          ),
          validator: (v) =>
              (v ?? '').isEmpty ? '$label est obligatoire.' : null,
        ),
      ],
    );
  }

  Widget _responsivePair(Widget left, Widget right) {
    return LayoutBuilder(
      builder: (context, c) {
        if (c.maxWidth < 340) {
          return Column(children: [left, const SizedBox(height: 14), right]);
        }
        return Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Expanded(child: left),
            const SizedBox(width: 14),
            Expanded(child: right),
          ],
        );
      },
    );
  }

  String? _categoryLabel() {
    if (_mainCategory == null || _mainCategory!.isEmpty) return null;
    for (final item in _categories) {
      if ('${item['slug'] ?? item['id']}' == _mainCategory) {
        return _cleanUiText(item['name'] ?? item['label'] ?? 'Catégorie');
      }
    }
    return null;
  }
}

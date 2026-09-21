import 'dart:async';
import 'dart:io';

import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../../../core/navigation/commercial_tab_bus.dart';
import 'package:geolocator/geolocator.dart';
import 'package:image_picker/image_picker.dart';

import '../../../core/api/api_exception.dart';
import '../../../core/theme/ovanie_colors.dart';
import '../../../core/widgets/brand_background.dart';
import '../../clients/data/clients_service.dart';
import '../../clients/presentation/client_chrome.dart';
import '../../clients/presentation/clients_screen.dart';
import '../../dashboard/models/dashboard_data.dart';
import '../data/shops_service.dart';
import '../models/shop_data.dart';
import 'shop_chrome.dart';
import 'shop_created_screen.dart';

class CommercialOpenShopScreen extends StatefulWidget {
  const CommercialOpenShopScreen({
    super.key,
    required this.shopsService,
    required this.clientsService,
    required this.initialUser,
    required this.unreadNotifications,
    required this.onLogout,
    this.prospectPrefill,
  });

  final ShopsService shopsService;
  final ClientsService clientsService;
  final Map<String, dynamic> initialUser;
  final int unreadNotifications;
  final Future<void> Function() onLogout;
  final Map<String, dynamic>? prospectPrefill;

  @override
  State<CommercialOpenShopScreen> createState() => _CommercialOpenShopScreenState();
}

class _CommercialOpenShopScreenState extends State<CommercialOpenShopScreen> {
  final ImagePicker _picker = ImagePicker();
  final TextEditingController _search = TextEditingController();

  final _fullName = TextEditingController();
  final _email = TextEditingController();
  final _phone = TextEditingController();
  final _whatsapp = TextEditingController();
  final _password = TextEditingController();
  final _passwordConfirmation = TextEditingController();
  final _responsibleNotes = TextEditingController();

  final _shopName = TextEditingController();
  final _description = TextEditingController();
  final _displayName = TextEditingController();
  final _shopNotes = TextEditingController();

  final _landmark = TextEditingController();
  final _address = TextEditingController();
  final _deliveryZoneText = TextEditingController();
  final _locationNotes = TextEditingController();

  final _identityNumber = TextEditingController();
  final _identityNotes = TextEditingController();

  final _payoutHolder = TextEditingController();
  final _payoutNumber = TextEditingController();
  final _payoutNotes = TextEditingController();

  int _step = 1;
  bool _returningToRecap = false;
  ShopMetaData? _meta;
  bool _loadingMeta = true;
  bool _submitting = false;
  bool _passwordVisible = false;
  bool _confirmationVisible = false;
  String? _error;

  String _sellerType = 'entreprise';
  String _phoneCountry = '+225';
  String _whatsappCountry = '+225';
  String? _selfiePath;

  ShopCategoryOption? _category;
  ShopCategoryOption? _subcategory;
  String? _logoPath;

  String _region = 'Abidjan';
  String _city = 'Abidjan';
  CommuneOption? _commune;
  QuarterOption? _quarter;
  String _logisticsType = 'ovanie';
  String _deliveryZone = 'grand_abidjan';
  double? _latitude;
  double? _longitude;
  double? _accuracy;
  String _geoSource = 'browser_gps';
  bool _locating = false;

  String _identityCountry = 'ci';
  String _identityType = 'cni';
  String? _identityFrontPath;
  String? _identityBackPath;
  String? _identityPdfPath;

  String _paymentMode = 'post_delivery';
  String _payoutMethod = 'wave';
  String _payoutCountry = '+225';
  bool _payoutConfirmed = true;
  bool _recapConfirmed = true;

  CommercialProfile get _profile => CommercialProfile.fromJson(widget.initialUser);


  Map<String, String> _metaOptionMap(List<Map<String, String>>? items) {
    if (items == null || items.isEmpty) return const <String, String>{};
    final result = <String, String>{};
    for (final item in items) {
      final code = (item['code'] ?? item['value'] ?? '').trim();
      final label = (item['label'] ?? code).trim();
      if (code.isNotEmpty && label.isNotEmpty) result[code] = label;
    }
    return result;
  }

  Map<String, String> get _sellerTypeOptions {
    final all = _metaOptionMap(_meta?.sellerTypes);
    // Le contrôleur Commercial actuel n'accepte encore que ces deux valeurs.
    // Les libellés viennent néanmoins exclusivement de Laravel.
    return <String, String>{
      for (final code in const <String>['particulier', 'entreprise'])
        if (all.containsKey(code)) code: all[code]!,
    };
  }

  Map<String, String> get _deliveryZoneOptions =>
      _metaOptionMap(_meta?.deliveryZones);

  Map<String, String> get _identityTypeOptions =>
      _metaOptionMap(_meta?.identityTypes);

  Map<String, String> get _identityCountryOptions =>
      _metaOptionMap(_meta?.identityCountries);

  Map<String, String> get _paymentModeOptions =>
      _metaOptionMap(_meta?.paymentModes);

  Map<String, String> get _payoutMethodOptions =>
      _metaOptionMap(_meta?.payoutMethods);

  int? _prefillInt(String key) {
    final value = widget.prospectPrefill?[key];
    if (value is num) return value.toInt();
    return int.tryParse('${value ?? ''}');
  }

  bool get _fromProspectingMission => _prefillInt('prospecting_mission_id') != null;
  int? get _prospectingMissionId => _prefillInt('prospecting_mission_id');
  int? get _prospectingQuarterId => _prefillInt('prospecting_quarter_id');
  String get _missionCommuneName => '${widget.prospectPrefill?['mission_commune_name'] ?? ''}'.trim();
  String get _missionQuarterName => '${widget.prospectPrefill?['mission_quarter_name'] ?? ''}'.trim();

  @override
  void initState() {
    super.initState();
    _applyProspectPrefill();
    _loadMeta();
  }

  void _applyProspectPrefill() {
    final p = widget.prospectPrefill;
    if (p == null) return;
    final contact = '${p['contact_name'] ?? ''}'.trim();
    if (contact.isNotEmpty) _fullName.text = contact;
    _shopName.text = '${p['business_name'] ?? ''}'.trim();
    _displayName.text = _shopName.text;
    String localPhone(dynamic raw) {
      var value = '${raw ?? ''}'.replaceAll(RegExp(r'[^0-9+]'), '');
      if (value.startsWith('+225')) value = value.substring(4);
      if (value.startsWith('225') && value.length > 10) value = value.substring(3);
      return value;
    }
    _phone.text = localPhone(p['phone']);
    _whatsapp.text = localPhone(p['whatsapp']);
    _address.text = '${p['address'] ?? ''}'.trim();
    _landmark.text = '${p['landmark'] ?? ''}'.trim();
    _latitude = p['latitude'] is num ? (p['latitude'] as num).toDouble() : double.tryParse('${p['latitude'] ?? ''}');
    _longitude = p['longitude'] is num ? (p['longitude'] as num).toDouble() : double.tryParse('${p['longitude'] ?? ''}');
  }

  @override
  void dispose() {
    for (final controller in [
      _search,
      _fullName,
      _email,
      _phone,
      _whatsapp,
      _password,
      _passwordConfirmation,
      _responsibleNotes,
      _shopName,
      _description,
      _displayName,
      _shopNotes,
      _landmark,
      _address,
      _deliveryZoneText,
      _locationNotes,
      _identityNumber,
      _identityNotes,
      _payoutHolder,
      _payoutNumber,
      _payoutNotes,
    ]) {
      controller.dispose();
    }
    super.dispose();
  }

  Future<void> _loadMeta() async {
    try {
      final meta = await widget.shopsService.meta();
      if (!mounted) return;
      setState(() {
        _meta = meta;
        _loadingMeta = false;
        if (meta.regions.isNotEmpty) _region = meta.regions.first;
        if (meta.cities.isNotEmpty) _city = meta.cities.first;
        final sellerTypes = _sellerTypeOptions;
        if (!sellerTypes.containsKey(_sellerType) && sellerTypes.isNotEmpty) {
          _sellerType = sellerTypes.keys.first;
        }
        final deliveryZones = _deliveryZoneOptions;
        if (!deliveryZones.containsKey(_deliveryZone) &&
            deliveryZones.isNotEmpty) {
          _deliveryZone = deliveryZones.keys.first;
        }
        final identityTypes = _identityTypeOptions;
        if (!identityTypes.containsKey(_identityType) &&
            identityTypes.isNotEmpty) {
          _identityType = identityTypes.keys.first;
        }
        final identityCountries = _identityCountryOptions;
        if (!identityCountries.containsKey(_identityCountry) &&
            identityCountries.isNotEmpty) {
          _identityCountry = identityCountries.keys.first;
        }
        final paymentModes = _paymentModeOptions;
        if (!paymentModes.containsKey(_paymentMode) && paymentModes.isNotEmpty) {
          _paymentMode = paymentModes.keys.first;
        }
        final payoutMethods = _payoutMethodOptions;
        if (!payoutMethods.containsKey(_payoutMethod) &&
            payoutMethods.isNotEmpty) {
          _payoutMethod = payoutMethods.keys.first;
        }
        final p = widget.prospectPrefill;
        if (p != null) {
          final communeId = p['commune_id'] is num ? (p['commune_id'] as num).toInt() : int.tryParse('${p['commune_id'] ?? ''}');
          final quarterId = p['quarter_id'] is num ? (p['quarter_id'] as num).toInt() : int.tryParse('${p['quarter_id'] ?? ''}');
          if (communeId != null) {
            for (final c in meta.communes) {
              if (c.id == communeId) { _commune = c; break; }
            }
          }
          if (_commune != null && quarterId != null) {
            for (final q in _commune!.quarters) {
              if (q.id == quarterId) { _quarter = q; break; }
            }
          }
        }
      });
    } on ApiException catch (error) {
      if (!mounted) return;
      setState(() {
        _loadingMeta = false;
        _error = error.message;
      });
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _loadingMeta = false;
        _error = 'Impossible de charger les catégories et localités OVANIE.';
      });
    }
  }

  Future<String?> _pickImage(ImageSource source) async {
    final file = await _picker.pickImage(
      source: source,
      imageQuality: 88,
      maxWidth: 1800,
      maxHeight: 1800,
    );
    return file?.path;
  }

  Future<void> _choosePhoto({required ValueChanged<String?> setPath}) async {
    final source = await showModalBottomSheet<ImageSource>(
      context: context,
      backgroundColor: const Color(0xFF071E43),
      showDragHandle: true,
      builder: (context) => SafeArea(
        top: false,
        child: Padding(
          padding: const EdgeInsets.fromLTRB(18, 0, 18, 18),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              ListTile(
                onTap: () => Navigator.pop(context, ImageSource.camera),
                leading: const Icon(Icons.photo_camera_outlined, color: Colors.white),
                title: const Text('Prendre une photo', style: TextStyle(color: Colors.white)),
              ),
              ListTile(
                onTap: () => Navigator.pop(context, ImageSource.gallery),
                leading: const Icon(Icons.photo_library_outlined, color: Colors.white),
                title: const Text('Importer depuis la galerie', style: TextStyle(color: Colors.white)),
              ),
            ],
          ),
        ),
      ),
    );
    if (source == null) return;
    final path = await _pickImage(source);
    if (!mounted || path == null) return;
    setState(() => setPath(path));
  }

  Future<void> _pickPdf() async {
    final result = await FilePicker.platform.pickFiles(
      type: FileType.custom,
      allowedExtensions: const ['pdf'],
      allowMultiple: false,
    );
    final path = result?.files.single.path;
    if (!mounted || path == null) return;
    setState(() => _identityPdfPath = path);
  }

  Future<void> _usePosition() async {
    setState(() {
      _locating = true;
      _error = null;
    });
    try {
      final serviceEnabled = await Geolocator.isLocationServiceEnabled();
      if (!serviceEnabled) {
        throw Exception('Activez la localisation (GPS) dans les réglages de votre téléphone puis réessayez.');
      }
      var permission = await Geolocator.checkPermission();
      if (permission == LocationPermission.denied) {
        permission = await Geolocator.requestPermission();
      }
      if (permission == LocationPermission.denied || permission == LocationPermission.deniedForever) {
        throw Exception('Autorisez la localisation dans les réglages du téléphone pour enregistrer la boutique.');
      }
      Position position;
      try {
        position = await Geolocator.getCurrentPosition(
          locationSettings: const LocationSettings(accuracy: LocationAccuracy.high, timeLimit: Duration(seconds: 20)),
        );
      } on TimeoutException {
        position = await Geolocator.getCurrentPosition(
          locationSettings: const LocationSettings(accuracy: LocationAccuracy.medium, timeLimit: Duration(seconds: 20)),
        );
      }
      if (!mounted) return;
      setState(() {
        _latitude = position.latitude;
        _longitude = position.longitude;
        _accuracy = position.accuracy;
        _geoSource = 'browser_gps';
      });
      unawaited(_autofillFromPosition(position.latitude, position.longitude));
    } on TimeoutException {
      if (!mounted) return;
      setState(() => _error = 'Signal GPS introuvable. Sortez à l’extérieur ou réessayez dans quelques secondes.');
    } catch (error) {
      if (!mounted) return;
      setState(() => _error = error.toString().replaceFirst('Exception: ', ''));
    } finally {
      if (mounted) setState(() => _locating = false);
    }
  }

  void _selectOvanieLogistics() {
    final alreadySelected = _logisticsType == 'ovanie';
    setState(() => _logisticsType = 'ovanie');
    if (!alreadySelected || _latitude == null || _longitude == null) {
      unawaited(_usePosition());
    }
  }

  Future<void> _autofillFromPosition(double latitude, double longitude) async {
    try {
      final result = await widget.shopsService.reverseGeocode(latitude: latitude, longitude: longitude);
      if (!mounted) return;

      final communes = _meta?.communes ?? const <CommuneOption>[];
      CommuneOption? matchedCommune;
      if (result.commune != null && result.commune!.trim().isNotEmpty) {
        final needle = result.commune!.trim().toLowerCase();
        for (final commune in communes) {
          if (commune.name.trim().toLowerCase() == needle) {
            matchedCommune = commune;
            break;
          }
        }
      }

      QuarterOption? matchedQuarter;
      final quarterSource = matchedCommune ?? _commune;
      if (quarterSource != null && result.quarter != null && result.quarter!.trim().isNotEmpty) {
        final needle = result.quarter!.trim().toLowerCase();
        for (final quarter in quarterSource.quarters) {
          if (quarter.name.trim().toLowerCase() == needle) {
            matchedQuarter = quarter;
            break;
          }
        }
      }

      setState(() {
        // En prospection, la commune et le quartier sont imposés par la mission OVANIE.
        // Le GPS sert uniquement à enregistrer la position exacte et à compléter
        // l'adresse / le point de repère ; il ne peut pas déplacer la boutique
        // hors de la zone affectée au commercial.
        if (!_fromProspectingMission && matchedCommune != null) {
          _commune = matchedCommune;
          _quarter = matchedQuarter;
        }
        if (_landmark.text.trim().isEmpty && (result.landmark ?? '').trim().isNotEmpty) {
          _landmark.text = result.landmark!.trim();
        }
        if (_address.text.trim().isEmpty && (result.address ?? '').trim().isNotEmpty) {
          _address.text = result.address!.trim();
        }
      });
    } catch (_) {
      // La géolocalisation inverse est un confort ; en cas d'échec le commercial
      // saisit simplement ces champs manuellement.
    }
  }

  String get _title => switch (_step) {
        1 || 2 => 'Ouvrir une boutique',
        3 => 'Ouverture boutique - Localisation & logistique',
        4 => 'Ouverture boutique - Identité',
        5 => 'Ouverture boutique - Reversement',
        _ => 'Récapitulatif boutique',
      };

  String get _subtitle => _step == 6
      ? 'Vérifiez toutes les informations avant la création de la boutique'
      : 'Créez un nouveau vendeur et sa boutique depuis le terrain';

  void _future(String title) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text('$title : fonctionnalité prévue dans un prochain lot.'), behavior: SnackBarBehavior.floating),
    );
  }

  void _nav(int index) {
    if (index == 2) return;
    CommercialTabBus.request(index);
  }

  Future<void> _showProfileMenu() async {
    final action = await showModalBottomSheet<String>(
      context: context,
      backgroundColor: const Color(0xFF071E43),
      showDragHandle: true,
      builder: (context) => SafeArea(
        top: false,
        child: Padding(
          padding: const EdgeInsets.fromLTRB(20, 0, 20, 20),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Text(_profile.name, style: const TextStyle(color: Colors.white, fontSize: 18, fontWeight: FontWeight.w800)),
              const SizedBox(height: 4),
              const Text('Espace Commercial OVANIE', style: TextStyle(color: Color(0xFF9FB0CF))),
              const SizedBox(height: 16),
              OutlinedButton.icon(
                onPressed: () => Navigator.pop(context, 'logout'),
                icon: const Icon(Icons.logout_rounded),
                label: const Text('Se déconnecter'),
              ),
            ],
          ),
        ),
      ),
    );
    if (action == 'logout') await widget.onLogout();
  }

  bool _validateStep() {
    String? message;
    if (_step == 1) {
      final phoneDigits = _phone.text.replaceAll(RegExp(r'\D'), '');
      if (_fullName.text.trim().length < 3) {
        message = 'Saisissez le nom complet du responsable.';
      } else if (!RegExp(r'^[^@\s]+@[^@\s]+\.[^@\s]+$').hasMatch(_email.text.trim())) {
        message = 'Saisissez une adresse e-mail valide.';
      } else if (phoneDigits.length < 8) {
        message = 'Saisissez un numéro de téléphone valide.';
      } else if (_password.text.length < 8 || !RegExp(r'[A-Za-z]').hasMatch(_password.text) || !RegExp(r'\d').hasMatch(_password.text)) {
        message = 'Le mot de passe doit contenir au moins 8 caractères, avec des lettres et des chiffres.';
      } else if (_password.text != _passwordConfirmation.text) {
        message = 'La confirmation du mot de passe ne correspond pas.';
      } else if (_selfiePath == null) {
        message = 'Ajoutez la photo ou le selfie du responsable.';
      }
    } else if (_step == 2) {
      if (_shopName.text.trim().isEmpty) {
        message = 'Saisissez le nom de la boutique.';
      } else if (_category == null) {
        message = 'Sélectionnez la catégorie principale.';
      } else if (_subcategory == null && (_category?.children.isNotEmpty ?? false)) {
        message = 'Sélectionnez une sous-catégorie.';
      } else if (_description.text.trim().isEmpty) {
        message = 'Ajoutez une courte description de la boutique.';
      }
    } else if (_step == 3) {
      if (_region.trim().isEmpty || _city.trim().isEmpty) {
        message = 'Sélectionnez la région et la ville.';
      } else if (_commune == null) {
        message = 'Sélectionnez la commune.';
      } else if (_quarter == null) {
        message = 'Sélectionnez le quartier.';
      } else if (_address.text.trim().isEmpty) {
        message = 'Saisissez l’adresse complète de la boutique.';
      } else if (_logisticsType == 'ovanie' && (_latitude == null || _longitude == null)) {
        message = 'Utilisez la position GPS ou indiquez les coordonnées de la boutique.';
      }
    } else if (_step == 4) {
      if (_identityNumber.text.trim().length < 6) {
        message = 'Saisissez un numéro de pièce valide.';
      } else if (_identityPdfPath == null && (_identityFrontPath == null || _identityBackPath == null)) {
        message = 'Ajoutez le recto et le verso de la pièce, ou importez un PDF.';
      }
    } else if (_step == 5) {
      if (_payoutHolder.text.trim().isEmpty) {
        message = 'Saisissez le nom du titulaire du moyen de reversement.';
      } else if (_payoutNumber.text.trim().isEmpty) {
        message = _payoutMethod == 'bank' ? 'Saisissez le numéro de compte bancaire.' : 'Saisissez le numéro Mobile Money.';
      } else if (!_payoutConfirmed) {
        message = 'Confirmez les informations de reversement avec le vendeur.';
      }
    } else if (_step == 6 && !_recapConfirmed) {
      message = 'Confirmez l’exactitude des informations saisies.';
    }
    setState(() => _error = message);
    return message == null;
  }

  void _next() {
    FocusScope.of(context).unfocus();
    if (!_validateStep()) return;
    if (_returningToRecap) {
      setState(() {
        _returningToRecap = false;
        _step = 6;
      });
      return;
    }
    if (_step < 6) setState(() => _step++);
  }

  void _previous() {
    FocusScope.of(context).unfocus();
    if (_returningToRecap) {
      setState(() {
        _returningToRecap = false;
        _step = 6;
        _error = null;
      });
      return;
    }
    if (_step > 1) {
      setState(() {
        _step--;
        _error = null;
      });
    } else {
      Navigator.of(context).pop();
    }
  }

  Future<void> _createShop() async {
    if (!_validateStep()) return;
    final fullName = _fullName.text.trim().replaceAll(RegExp(r'\s+'), ' ');
    final parts = fullName.split(' ');
    final firstName = parts.isEmpty ? fullName : parts.first;
    final lastName = parts.length > 1 ? parts.sublist(1).join(' ') : '';
    setState(() {
      _submitting = true;
      _error = null;
    });

    try {
      final result = await widget.shopsService.create(
        fields: {
          'prospect_id': widget.prospectPrefill?['id'],
          'prospecting_mission_id': _prospectingMissionId,
          'prospecting_quarter_id': _prospectingQuarterId,
          'seller_type': _sellerType,
          'first_name': firstName,
          'last_name': lastName,
          'full_name': fullName,
          'email': _email.text.trim(),
          'phone_country': _phoneCountry,
          'phone': _phone.text.trim(),
          'whatsapp_country': _whatsappCountry,
          'whatsapp': _whatsapp.text.trim(),
          'password': _password.text,
          'password_confirmation': _passwordConfirmation.text,
          'responsible_notes': _responsibleNotes.text.trim(),
          'shop_name': _shopName.text.trim(),
          'display_name': _displayName.text.trim().isEmpty ? _shopName.text.trim() : _displayName.text.trim(),
          'main_category': _category?.name,
          'main_category_id': _category?.id,
          'main_subcategory': _subcategory?.name,
          'main_subcategory_id': _subcategory?.id,
          'description': _description.text.trim(),
          'shop_notes': _shopNotes.text.trim(),
          'region': _region,
          'city': _city,
          'commune': _commune?.name,
          'commune_id': _commune?.id,
          'district': _quarter?.name,
          'quarter_id': _quarter?.id,
          'landmark': _landmark.text.trim(),
          'address': _address.text.trim(),
          'latitude': _latitude,
          'longitude': _longitude,
          'geo_accuracy': _accuracy,
          'geo_source': _geoSource,
          'logistics_type': _logisticsType,
          'delivery_zone': _deliveryZone,
          'delivery_zone_text': _deliveryZoneText.text.trim(),
          'location_notes': _locationNotes.text.trim(),
          'identity_country': _identityCountry,
          'identity_type': _identityType,
          'identity_number': _identityNumber.text.trim().toUpperCase(),
          'identity_notes': _identityNotes.text.trim(),
          'payment_mode': _paymentMode,
          'payout_method': _payoutMethod,
          'payout_holder': _payoutHolder.text.trim(),
          'payout_number': _payoutNumber.text.trim(),
          'payout_confirmed': _payoutConfirmed,
          'payout_notes': _payoutNotes.text.trim(),
        },
        files: {
          'selfie': _selfiePath,
          'logo': _logoPath,
          'identity_file_front': _identityFrontPath,
          'identity_file_back': _identityBackPath,
          'identity_file': _identityPdfPath,
        },
      );
      if (!mounted) return;
      final done = await Navigator.of(context).push<bool>(
        MaterialPageRoute(
          builder: (_) => CommercialShopCreatedScreen(
            result: result,
            shopsService: widget.shopsService,
            clientsService: widget.clientsService,
            initialUser: widget.initialUser,
            unreadNotifications: widget.unreadNotifications,
            onLogout: widget.onLogout,
          ),
        ),
      );
      if (!mounted) return;
      if (done == true) Navigator.of(context).pop(true);
    } on ApiException catch (error) {
      if (!mounted) return;
      setState(() => _error = error.message);
    } catch (_) {
      if (!mounted) return;
      setState(() => _error = 'Impossible de créer la boutique pour le moment.');
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final width = MediaQuery.sizeOf(context).width;
    final scale = (width / 472).clamp(.78, 1.08).toDouble();
    double s(double value) => value * scale;

    return Scaffold(
      body: BrandBackground(
        child: SafeArea(
          bottom: false,
          child: CustomScrollView(
            keyboardDismissBehavior: ScrollViewKeyboardDismissBehavior.onDrag,
            slivers: [
              SliverPadding(
                padding: EdgeInsets.fromLTRB(s(15), s(9), s(15), s(100)),
                sliver: SliverList(
                  delegate: SliverChildListDelegate.fixed([
                    CommercialClientsHeader(
                      profile: _profile,
                      unreadNotifications: widget.unreadNotifications,
                      scale: scale,
                      onNotificationsTap: () => _future('Notifications'),
                      onAvatarTap: _showProfileMenu,
                    ),
                    SizedBox(height: s(13)),
                    CommercialShopSearch(
                      controller: _search,
                      scale: scale,
                      onChanged: (_) => setState(() {}),
                      onFilterTap: () => _future('Filtres boutiques'),
                    ),
                    SizedBox(height: s(13)),
                    Text(
                      _title,
                      style: TextStyle(color: Colors.white, fontSize: s(_step == 3 || _step == 4 || _step == 5 ? 20.5 : 22.3), fontWeight: FontWeight.w800, letterSpacing: -.4),
                    ),
                    SizedBox(height: s(3)),
                    Text(_subtitle, style: TextStyle(color: const Color(0xFFC8D1E1), fontSize: s(10.6))),
                    SizedBox(height: s(13)),
                    Container(
                      padding: EdgeInsets.fromLTRB(s(14), s(12), s(14), s(13)),
                      decoration: BoxDecoration(color: const Color(0xFFFCFDFE), borderRadius: BorderRadius.circular(s(12)), boxShadow: [BoxShadow(color: Colors.black.withOpacity(.18), blurRadius: 12, offset: const Offset(0, 5))]),
                      child: _loadingMeta
                          ? Padding(padding: EdgeInsets.all(s(35)), child: const Center(child: CircularProgressIndicator()))
                          : Column(
                              crossAxisAlignment: CrossAxisAlignment.stretch,
                              children: [
                                _ShopStepper(step: _step, scale: scale),
                                SizedBox(height: s(15)),
                                if (_error != null) ...[
                                  _InlineError(message: _error!, scale: scale),
                                  SizedBox(height: s(10)),
                                ],
                                _stepBody(scale),
                              ],
                            ),
                    ),
                    SizedBox(height: s(8)),
                    _InfoBar(step: _step, scale: scale),
                  ]),
                ),
              ),
            ],
          ),
        ),
      ),
      extendBody: true,
      bottomNavigationBar: CommercialBottomNavigation(scale: scale, currentIndex: 2, onTap: _nav),
    );
  }

  Widget _stepBody(double scale) => switch (_step) {
        1 => _responsibleStep(scale),
        2 => _shopStep(scale),
        3 => _locationStep(scale),
        4 => _identityStep(scale),
        5 => _payoutStep(scale),
        _ => _recapStep(scale),
      };

  Widget _responsibleStep(double scale) {
    double s(double value) => value * scale;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        _StepHeading(title: 'Responsable de la boutique', subtitle: 'Renseignez les informations du propriétaire ou du gérant', scale: scale),
        SizedBox(height: s(11)),
        _Dropdown<String>(
          label: 'Type de vendeur',
          hint: 'Sélectionner un type de vendeur',
          value: _sellerTypeOptions.containsKey(_sellerType) ? _sellerType : null,
          items: _sellerTypeOptions.keys.toList(growable: false),
          text: (value) => _sellerTypeOptions[value] ?? value,
          scale: scale,
          onChanged: (value) => setState(() => _sellerType = value ?? _sellerType),
        ),
        SizedBox(height: s(10)),
        _Input(label: 'Nom complet', hint: 'Ex: Kouassi Yao Jean', controller: _fullName, scale: scale),
        _Input(label: 'E-mail', hint: 'responsable@email.com', controller: _email, keyboardType: TextInputType.emailAddress, scale: scale),
        Row(
          children: [
            Expanded(child: _PhoneInput(label: 'Téléphone', country: _phoneCountry, controller: _phone, scale: scale, onCountry: (v) => setState(() => _phoneCountry = v))),
            SizedBox(width: s(10)),
            Expanded(child: _PhoneInput(label: 'WhatsApp', country: _whatsappCountry, controller: _whatsapp, scale: scale, onCountry: (v) => setState(() => _whatsappCountry = v))),
          ],
        ),
        _PasswordInput(label: 'Mot de passe temporaire', controller: _password, visible: _passwordVisible, onToggle: () => setState(() => _passwordVisible = !_passwordVisible), scale: scale),
        _PasswordInput(label: 'Confirmer le mot de passe', controller: _passwordConfirmation, visible: _confirmationVisible, onToggle: () => setState(() => _confirmationVisible = !_confirmationVisible), scale: scale),
        _FieldLabel('Photo / selfie du responsable', scale: scale),
        SizedBox(height: s(5)),
        _PhotoUpload(
          path: _selfiePath,
          scale: scale,
          icon: Icons.person_rounded,
          onCamera: () async {
            final path = await _pickImage(ImageSource.camera);
            if (mounted && path != null) setState(() => _selfiePath = path);
          },
          onImport: () async {
            final path = await _pickImage(ImageSource.gallery);
            if (mounted && path != null) setState(() => _selfiePath = path);
          },
        ),
        SizedBox(height: s(10)),
        _Buttons(previousLabel: 'Annuler', nextLabel: 'Continuer', scale: scale, onPrevious: _previous, onNext: _next),
      ],
    );
  }

  Widget _shopStep(double scale) {
    double s(double value) => value * scale;
    final categories = _meta?.categories ?? const <ShopCategoryOption>[];
    final children = _category?.children ?? const <ShopCategoryOption>[];
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        _StepHeading(title: 'Informations de la boutique', subtitle: 'Renseignez les informations principales de la boutique', scale: scale),
        SizedBox(height: s(10)),
        _Input(label: 'Nom de la boutique', hint: 'Ex: Quincaillerie Kouassi', controller: _shopName, scale: scale),
        Row(
          children: [
            Expanded(
              child: _Dropdown<ShopCategoryOption>(
                label: 'Catégorie principale',
                hint: 'Sélectionner une catégorie',
                value: _category,
                items: categories,
                text: (v) => v.name,
                scale: scale,
                onChanged: (value) => setState(() {
                  _category = value;
                  _subcategory = null;
                }),
              ),
            ),
            SizedBox(width: s(10)),
            Expanded(
              child: _Dropdown<ShopCategoryOption>(
                label: 'Sous-catégorie',
                hint: children.isEmpty ? 'Aucune sous-catégorie' : 'Sélectionner une sous-catégorie',
                value: _subcategory,
                items: children,
                text: (v) => v.name,
                scale: scale,
                onChanged: children.isEmpty ? null : (value) => setState(() => _subcategory = value),
              ),
            ),
          ],
        ),
        _NotesInput(label: 'Description de la boutique', controller: _description, hint: 'Décrivez brièvement votre boutique, les produits et services proposés...', maxLength: 500, scale: scale),
        _Input(label: 'Nom affiché sur OVANIE', hint: 'Ex: Bâtir Plus', controller: _displayName, scale: scale),
        _FieldLabel('Photo ou logo de la boutique', scale: scale),
        SizedBox(height: s(5)),
        _PhotoUpload(
          path: _logoPath,
          scale: scale,
          icon: Icons.storefront_outlined,
          onCamera: () async {
            final path = await _pickImage(ImageSource.camera);
            if (mounted && path != null) setState(() => _logoPath = path);
          },
          onImport: () async {
            final path = await _pickImage(ImageSource.gallery);
            if (mounted && path != null) setState(() => _logoPath = path);
          },
        ),
        SizedBox(height: s(10)),
        _Buttons(previousLabel: 'Précédent', nextLabel: 'Continuer', scale: scale, onPrevious: _previous, onNext: _next),
      ],
    );
  }

  Widget _locationStep(double scale) {
    double s(double value) => value * scale;
    final regions = _meta?.regions ?? const <String>[];
    final cities = _meta?.cities ?? const <String>[];
    final communes = _meta?.communes ?? const <CommuneOption>[];
    final quarters = _commune?.quarters ?? const <QuarterOption>[];
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        _StepHeading(title: 'Localisation & logistique', subtitle: 'Renseignez l’emplacement de la boutique et son mode de livraison', scale: scale),
        SizedBox(height: s(10)),
        _FieldLabel('Mode logistique', scale: scale),
        SizedBox(height: s(5)),
        Row(
          children: [
            Expanded(child: _ChoiceCard(selected: _logisticsType == 'ovanie', icon: Icons.local_shipping_outlined, title: 'OVANIE Logistics', subtitle: 'OVANIE gère la logistique de la boutique', scale: scale, onTap: _selectOvanieLogistics)),
            SizedBox(width: s(10)),
            Expanded(child: _ChoiceCard(selected: _logisticsType == 'seller', icon: Icons.local_shipping_outlined, title: 'Logistique vendeur', subtitle: 'Le vendeur gère lui-même ses livraisons', scale: scale, onTap: () => setState(() => _logisticsType = 'seller'))),
          ],
        ),
        if (_locating) ...[
          SizedBox(height: s(6)),
          Row(children: [
            SizedBox(width: s(12), height: s(12), child: const CircularProgressIndicator(strokeWidth: 2)),
            SizedBox(width: s(6)),
            Text('Localisation en cours pour remplir commune, quartier, point de repère et adresse...', style: TextStyle(color: const Color(0xFF5A7196), fontSize: s(8.2))),
          ]),
        ],
        SizedBox(height: s(10)),
        if (_fromProspectingMission) ...[
          Container(
            margin: EdgeInsets.only(bottom: s(10)),
            padding: EdgeInsets.all(s(10)),
            decoration: BoxDecoration(
              color: const Color(0xFFEAF3FF),
              border: Border.all(color: const Color(0xFFCFE1F5)),
              borderRadius: BorderRadius.circular(s(8)),
            ),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Container(
                  width: s(28),
                  height: s(28),
                  decoration: BoxDecoration(color: const Color(0xFF0E65BA), borderRadius: BorderRadius.circular(s(7))),
                  child: Icon(Icons.location_on_rounded, color: Colors.white, size: s(16)),
                ),
                SizedBox(width: s(8)),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text('Zone imposée par la mission OVANIE', style: TextStyle(color: const Color(0xFF0D4E8F), fontSize: s(9.2), fontWeight: FontWeight.w800)),
                      SizedBox(height: s(2)),
                      Text(
                        [
                          if (_missionCommuneName.isNotEmpty) _missionCommuneName,
                          if (_missionQuarterName.isNotEmpty) _missionQuarterName,
                        ].join(' · '),
                        style: TextStyle(color: const Color(0xFF173A63), fontSize: s(9.8), fontWeight: FontWeight.w700),
                      ),
                      SizedBox(height: s(2)),
                      Text('La position GPS exacte sera enregistrée, mais la commune et le quartier ne peuvent pas être modifiés.', style: TextStyle(color: const Color(0xFF637895), fontSize: s(8.2), height: 1.35)),
                    ],
                  ),
                ),
              ],
            ),
          ),
        ],
        Row(
          children: [
            Expanded(child: _Dropdown<String>(label: 'Région', hint: 'Sélectionner une région', value: _region, items: regions, text: (v) => v, scale: scale, onChanged: (v) => setState(() => _region = v ?? _region))),
            SizedBox(width: s(9)),
            Expanded(child: _Dropdown<String>(label: 'Ville', hint: 'Sélectionner une ville', value: _city, items: cities, text: (v) => v, scale: scale, onChanged: (v) => setState(() => _city = v ?? _city))),
            SizedBox(width: s(9)),
            Expanded(child: _Dropdown<CommuneOption>(
              label: 'Commune',
              hint: 'Sélectionner une commune',
              value: _commune,
              items: communes,
              text: (v) => v.name,
              scale: scale,
              onChanged: _fromProspectingMission ? null : (v) => setState(() { _commune = v; _quarter = null; }),
            )),
          ],
        ),
        Row(
          children: [
            Expanded(child: _Dropdown<QuarterOption>(
              label: 'Quartier',
              hint: 'Sélectionner un quartier',
              value: _quarter,
              items: quarters,
              text: (v) => v.name,
              scale: scale,
              onChanged: _fromProspectingMission || quarters.isEmpty ? null : (v) => setState(() => _quarter = v),
            )),
            SizedBox(width: s(10)),
            Expanded(child: _Input(label: 'Point de repère', hint: 'Ex: Près du marché central', controller: _landmark, scale: scale, compact: true)),
          ],
        ),
        _Input(label: 'Adresse complète', hint: 'Ex: Rue 12, Porte 45, quartier, commune', controller: _address, scale: scale),
        _Dropdown<String>(
          label: 'Zone de livraison',
          hint: 'Sélectionner une zone',
          value: _deliveryZoneOptions.containsKey(_deliveryZone) ? _deliveryZone : null,
          items: _deliveryZoneOptions.keys.toList(growable: false),
          text: (v) => _deliveryZoneOptions[v] ?? v,
          scale: scale,
          onChanged: (v) => setState(() => _deliveryZone = v ?? _deliveryZone),
        ),
        SizedBox(height: s(10)),
        _Buttons(previousLabel: 'Précédent', nextLabel: 'Continuer', scale: scale, onPrevious: _previous, onNext: _next),
      ],
    );
  }

  Widget _identityStep(double scale) {
    double s(double value) => value * scale;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        _StepHeading(title: 'Vérification d’identité', subtitle: 'Renseignez les informations d’identification du vendeur', scale: scale),
        SizedBox(height: s(10)),
        Row(
          children: [
            Expanded(child: _Dropdown<String>(label: 'Pays de délivrance', hint: 'Sélectionner un pays', value: _identityCountryOptions.containsKey(_identityCountry) ? _identityCountry : null, items: _identityCountryOptions.keys.toList(growable: false), text: (v) => _identityCountryOptions[v] ?? v, scale: scale, onChanged: (v) => setState(() => _identityCountry = v ?? _identityCountry))),
            SizedBox(width: s(10)),
            Expanded(child: _Dropdown<String>(label: 'Type de pièce', hint: 'CNI, Passeport, Permis...', value: _identityTypeOptions.containsKey(_identityType) ? _identityType : null, items: _identityTypeOptions.keys.toList(growable: false), text: (v) => _identityTypeOptions[v] ?? v, scale: scale, onChanged: (v) => setState(() => _identityType = v ?? _identityType))),
          ],
        ),
        _Input(label: 'Numéro de pièce', hint: 'Ex: 1234567890', controller: _identityNumber, scale: scale),
        Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Expanded(child: _IdentityUpload(title: 'Recto', path: _identityFrontPath, scale: scale, onCamera: () async { final p = await _pickImage(ImageSource.camera); if (mounted && p != null) setState(() => _identityFrontPath = p); }, onImport: () async { final p = await _pickImage(ImageSource.gallery); if (mounted && p != null) setState(() => _identityFrontPath = p); })),
            SizedBox(width: s(10)),
            Expanded(child: _IdentityUpload(title: 'Verso', path: _identityBackPath, scale: scale, onCamera: () async { final p = await _pickImage(ImageSource.camera); if (mounted && p != null) setState(() => _identityBackPath = p); }, onImport: () async { final p = await _pickImage(ImageSource.gallery); if (mounted && p != null) setState(() => _identityBackPath = p); })),
          ],
        ),
        SizedBox(height: s(10)),
        _FieldLabel('Document PDF (optionnel)', scale: scale),
        SizedBox(height: s(5)),
        InkWell(
          onTap: _pickPdf,
          borderRadius: BorderRadius.circular(s(8)),
          child: Container(
            padding: EdgeInsets.all(s(12)),
            decoration: BoxDecoration(borderRadius: BorderRadius.circular(s(8)), border: Border.all(color: const Color(0xFFB7C6DB), style: BorderStyle.solid), color: const Color(0xFFFAFCFF)),
            child: Row(
              children: [
                Icon(Icons.picture_as_pdf_outlined, color: const Color(0xFF7188AD), size: s(34)),
                SizedBox(width: s(10)),
                Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text(_identityPdfPath == null ? 'Importer une copie scannée de la pièce d’identité' : _identityPdfPath!.split(RegExp(r'[/\\]')).last, style: TextStyle(color: const Color(0xFF213D6D), fontSize: s(9.5), fontWeight: FontWeight.w600)), SizedBox(height: s(2)), Text('PDF – Max 10 Mo', style: TextStyle(color: const Color(0xFF61789C), fontSize: s(8.5)))])),
                OutlinedButton.icon(onPressed: _pickPdf, icon: Icon(Icons.cloud_upload_outlined, size: s(16)), label: Text('Importer le PDF', style: TextStyle(fontSize: s(8.8))), style: OutlinedButton.styleFrom(foregroundColor: const Color(0xFF075EF0), side: const BorderSide(color: Color(0xFF075EF0)))),
              ],
            ),
          ),
        ),
        SizedBox(height: s(10)),
        _Buttons(previousLabel: 'Précédent', nextLabel: 'Continuer', scale: scale, onPrevious: _previous, onNext: _next),
      ],
    );
  }

  Widget _payoutStep(double scale) {
    double s(double value) => value * scale;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        _StepHeading(title: 'Informations de reversement', subtitle: 'Renseignez les informations de paiement du vendeur', scale: scale),
        SizedBox(height: s(11)),
        _FieldLabel('Mode de paiement vendeur', scale: scale),
        SizedBox(height: s(5)),
        Row(
          children: [
            Expanded(child: _ChoiceCard(selected: _paymentMode == 'post_delivery', icon: Icons.radio_button_checked, title: _paymentModeOptions['post_delivery'] ?? 'Paiement après livraison (72h)', subtitle: 'Les ventes sont payées 72h après livraison confirmée', scale: scale, onTap: () => setState(() => _paymentMode = 'post_delivery'))),
            SizedBox(width: s(10)),
            Expanded(child: _ChoiceCard(selected: _paymentMode == 'weekly', icon: Icons.radio_button_checked, title: _paymentModeOptions['weekly'] ?? 'Paiement hebdomadaire', subtitle: 'Les ventes sont regroupées et payées chaque semaine', scale: scale, onTap: () => setState(() => _paymentMode = 'weekly'))),
          ],
        ),
        SizedBox(height: s(12)),
        _FieldLabel('Moyen de reversement', scale: scale),
        SizedBox(height: s(6)),
        Row(
          children: [
            Expanded(child: _PayoutOption(value: 'orange', label: _payoutMethodOptions['orange'] ?? 'Orange Money', assetPath: 'assets/operators/orange.png', selected: _payoutMethod == 'orange', scale: scale, onTap: () => setState(() => _payoutMethod = 'orange'))),
            SizedBox(width: s(6)),
            Expanded(child: _PayoutOption(value: 'mtn', label: _payoutMethodOptions['mtn'] ?? 'MTN MoMo', assetPath: 'assets/operators/mtn.png', selected: _payoutMethod == 'mtn', scale: scale, onTap: () => setState(() => _payoutMethod = 'mtn'))),
            SizedBox(width: s(6)),
            Expanded(child: _PayoutOption(value: 'moov', label: _payoutMethodOptions['moov'] ?? 'Moov Money', assetPath: 'assets/operators/moov.png', selected: _payoutMethod == 'moov', scale: scale, onTap: () => setState(() => _payoutMethod = 'moov'))),
            SizedBox(width: s(6)),
            Expanded(child: _PayoutOption(value: 'wave', label: _payoutMethodOptions['wave'] ?? 'Wave', assetPath: 'assets/operators/wave.png', selected: _payoutMethod == 'wave', scale: scale, onTap: () => setState(() => _payoutMethod = 'wave'))),
            SizedBox(width: s(6)),
            Expanded(child: _PayoutOption(value: 'bank', label: _payoutMethodOptions['bank'] ?? 'Virement bancaire', icon: Icons.account_balance_outlined, selected: _payoutMethod == 'bank', scale: scale, onTap: () => setState(() => _payoutMethod = 'bank'))),
          ],
        ),
        SizedBox(height: s(10)),
        _Input(label: 'Nom du titulaire', hint: 'Ex: Kouassi Jean Philippe', controller: _payoutHolder, scale: scale),
        if (_payoutMethod == 'bank')
          _Input(label: 'Numéro de compte', hint: 'Ex: CI00 0000 0000 0000', controller: _payoutNumber, scale: scale)
        else
          _PhoneInput(label: 'Numéro de téléphone', country: _payoutCountry, controller: _payoutNumber, scale: scale, onCountry: (v) => setState(() => _payoutCountry = v)),
        CheckboxListTile(
          value: _payoutConfirmed,
          onChanged: (v) => setState(() => _payoutConfirmed = v ?? false),
          contentPadding: EdgeInsets.zero,
          controlAffinity: ListTileControlAffinity.leading,
          activeColor: const Color(0xFF086BF1),
          title: Text('Confirmer les informations de reversement avec le vendeur', style: TextStyle(color: const Color(0xFF0B2047), fontSize: s(9.6), fontWeight: FontWeight.w600)),
        ),
        SizedBox(height: s(10)),
        _Buttons(previousLabel: 'Précédent', nextLabel: 'Créer la boutique', scale: scale, onPrevious: _previous, onNext: _next),
      ],
    );
  }

  Widget _recapStep(double scale) {
    double s(double value) => value * scale;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        _RecapSection(
          title: 'Responsable',
          icon: Icons.person_outline_rounded,
          scale: scale,
          onEdit: () => setState(() { _step = 1; _returningToRecap = true; }),
          rows: [
            ('Type de vendeur', _sellerTypeOptions[_sellerType] ?? _sellerType),
            ('Nom complet', _fullName.text.trim()),
            ('E-mail', _email.text.trim()),
            ('Téléphone', '$_phoneCountry ${_phone.text.trim()}'),
            ('WhatsApp', '$_whatsappCountry ${_whatsapp.text.trim()}'),
            ('Selfie / photo', _selfiePath == null ? 'Non ajouté' : 'Ajouté'),
          ],
        ),
        SizedBox(height: s(6)),
        _RecapSection(
          title: 'Boutique',
          icon: Icons.storefront_outlined,
          scale: scale,
          onEdit: () => setState(() { _step = 2; _returningToRecap = true; }),
          rows: [
            ('Nom de la boutique', _shopName.text.trim()),
            ('Catégorie principale', _category?.name ?? ''),
            ('Sous-catégorie', _subcategory?.name ?? '—'),
            ('Nom affiché sur OVANIE', _displayName.text.trim().isEmpty ? _shopName.text.trim() : _displayName.text.trim()),
            ('Description', _description.text.trim()),
          ],
        ),
        SizedBox(height: s(6)),
        _RecapSection(
          title: 'Localisation & logistique',
          icon: Icons.location_on_outlined,
          scale: scale,
          onEdit: () => setState(() { _step = 3; _returningToRecap = true; }),
          rows: [
            ('Région', _region),
            ('Ville', _city),
            ('Commune', _commune?.name ?? ''),
            ('Quartier', _quarter?.name ?? ''),
            ('Point de repère', _landmark.text.trim()),
            ('Adresse complète', _address.text.trim()),
            ('Latitude', _latitude?.toStringAsFixed(7) ?? '—'),
            ('Longitude', _longitude?.toStringAsFixed(7) ?? '—'),
            ('Mode logistique', _logisticsType == 'ovanie' ? 'OVANIE Logistics' : 'Logistique vendeur'),
          ],
        ),
        SizedBox(height: s(6)),
        _RecapSection(
          title: 'Identité',
          icon: Icons.badge_outlined,
          scale: scale,
          onEdit: () => setState(() { _step = 4; _returningToRecap = true; }),
          rows: [
            ('Pays de délivrance', 'Côte d’Ivoire'),
            ('Type de pièce', _identityType.toUpperCase()),
            ('Numéro de pièce', _maskIdentity(_identityNumber.text.trim())),
            ('Recto / verso', _identityFrontPath != null && _identityBackPath != null ? 'Ajoutés' : '—'),
            ('PDF', _identityPdfPath == null ? '—' : 'Ajouté'),
          ],
        ),
        SizedBox(height: s(6)),
        _RecapSection(
          title: 'Reversement',
          icon: Icons.account_balance_wallet_outlined,
          scale: scale,
          onEdit: () => setState(() { _step = 5; _returningToRecap = true; }),
          rows: [
            ('Mode de paiement vendeur', _paymentMode == 'post_delivery' ? 'Paiement après livraison (72h)' : 'Paiement hebdomadaire'),
            ('Moyen de reversement', _payoutMethodLabel(_payoutMethod)),
            ('Nom du titulaire', _payoutHolder.text.trim()),
            ('Numéro de téléphone ou compte', _payoutNumber.text.trim()),
          ],
        ),
        CheckboxListTile(
          value: _recapConfirmed,
          onChanged: (v) => setState(() => _recapConfirmed = v ?? false),
          contentPadding: EdgeInsets.zero,
          controlAffinity: ListTileControlAffinity.leading,
          activeColor: const Color(0xFF086BF1),
          title: Text('Je confirme l’exactitude des informations saisies', style: TextStyle(color: const Color(0xFF0B2047), fontSize: s(9.6), fontWeight: FontWeight.w600)),
        ),
        _Buttons(
          previousLabel: 'Précédent',
          nextLabel: _submitting ? 'Création...' : 'Créer la boutique',
          scale: scale,
          onPrevious: _submitting ? () {} : _previous,
          onNext: _submitting ? () {} : _createShop,
          loading: _submitting,
        ),
      ],
    );
  }

  String _maskIdentity(String value) {
    if (value.length <= 6) return value;
    final stars = List.filled(value.length - 6, '*').join();
    return '${value.substring(0, 2)}$stars${value.substring(value.length - 4)}';
  }

  String _payoutMethodLabel(String value) => switch (value) {
        'orange' => 'Orange Money',
        'mtn' => 'MTN MoMo',
        'moov' => 'Moov Money',
        'wave' => 'Wave',
        'bank' => 'Virement bancaire',
        _ => value,
      };
}

class _ShopStepper extends StatelessWidget {
  const _ShopStepper({required this.step, required this.scale});
  final int step;
  final double scale;
  @override
  Widget build(BuildContext context) {
    double s(double value) => value * scale;
    final labels = step == 6
        ? const ['Responsable', 'Boutique', 'Localisation &\nlogistique', 'Identité', 'Reversement', 'Récapitulatif']
        : const ['Responsable', 'Boutique', 'Localisation &\nlogistique', 'Identité', 'Reversement'];
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: List.generate(labels.length, (index) {
        final number = index + 1;
        final done = number < step;
        final active = number == step;
        return Expanded(
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(
                child: Column(
                  children: [
                    Container(
                      width: s(30),
                      height: s(30),
                      decoration: BoxDecoration(
                        shape: BoxShape.circle,
                        color: done || active ? const Color(0xFF086AF2) : Colors.white,
                        border: Border.all(color: done || active ? const Color(0xFF086AF2) : const Color(0xFFB8C6DA)),
                      ),
                      alignment: Alignment.center,
                      child: done
                          ? Icon(Icons.check_rounded, color: Colors.white, size: s(18))
                          : Text('$number', style: TextStyle(color: active ? Colors.white : const Color(0xFF456083), fontSize: s(10.5), fontWeight: FontWeight.w700)),
                    ),
                    SizedBox(height: s(4)),
                    Text(labels[index], textAlign: TextAlign.center, style: TextStyle(color: done || active ? const Color(0xFF0765E8) : const Color(0xFF355078), fontSize: s(labels.length == 6 ? 7.4 : 8.2), fontWeight: done || active ? FontWeight.w700 : FontWeight.w500, height: 1.05)),
                  ],
                ),
              ),
              if (index < labels.length - 1)
                Container(
                  width: s(labels.length == 6 ? 18 : 28),
                  height: 1,
                  margin: EdgeInsets.only(top: s(15)),
                  color: number < step ? const Color(0xFF086AF2) : const Color(0xFFBCC9DA),
                ),
            ],
          ),
        );
      }),
    );
  }
}

class _StepHeading extends StatelessWidget {
  const _StepHeading({required this.title, required this.subtitle, required this.scale});
  final String title;
  final String subtitle;
  final double scale;
  @override
  Widget build(BuildContext context) {
    double s(double value) => value * scale;
    return Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      Text(title, style: TextStyle(color: const Color(0xFF091A3D), fontSize: s(13.8), fontWeight: FontWeight.w800)),
      SizedBox(height: s(2)),
      Text(subtitle, style: TextStyle(color: const Color(0xFF34527F), fontSize: s(9.7))),
    ]);
  }
}

class _FieldLabel extends StatelessWidget {
  const _FieldLabel(this.text, {required this.scale});
  final String text;
  final double scale;
  @override
  Widget build(BuildContext context) => Text(text, style: TextStyle(color: const Color(0xFF0E2046), fontSize: 9.5 * scale, fontWeight: FontWeight.w600));
}

class _Input extends StatelessWidget {
  const _Input({required this.label, required this.hint, required this.controller, required this.scale, this.keyboardType, this.compact = false});
  final String label;
  final String hint;
  final TextEditingController controller;
  final double scale;
  final TextInputType? keyboardType;
  final bool compact;
  @override
  Widget build(BuildContext context) {
    double s(double value) => value * scale;
    return Padding(
      padding: EdgeInsets.only(bottom: s(compact ? 0 : 9)),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        _FieldLabel(label, scale: scale),
        SizedBox(height: s(4)),
        TextField(
          controller: controller,
          keyboardType: keyboardType,
          style: TextStyle(color: const Color(0xFF14284D), fontSize: s(10.2)),
          decoration: InputDecoration(
            hintText: hint,
            hintStyle: TextStyle(color: const Color(0xFF8495B1), fontSize: s(9.8)),
            isDense: true,
            contentPadding: EdgeInsets.symmetric(horizontal: s(10), vertical: s(10)),
            filled: true,
            fillColor: const Color(0xFFFFFFFF),
            enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(s(6)), borderSide: const BorderSide(color: Color(0xFFC9D3E1))),
            focusedBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(s(6)), borderSide: const BorderSide(color: Color(0xFF086AF2))),
          ),
        ),
      ]),
    );
  }
}

class _PasswordInput extends StatelessWidget {
  const _PasswordInput({required this.label, required this.controller, required this.visible, required this.onToggle, required this.scale});
  final String label;
  final TextEditingController controller;
  final bool visible;
  final VoidCallback onToggle;
  final double scale;
  @override
  Widget build(BuildContext context) {
    double s(double value) => value * scale;
    return Padding(
      padding: EdgeInsets.only(bottom: s(9)),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        _FieldLabel(label, scale: scale),
        SizedBox(height: s(4)),
        TextField(
          controller: controller,
          obscureText: !visible,
          style: TextStyle(color: const Color(0xFF14284D), fontSize: s(10.2)),
          decoration: InputDecoration(
            isDense: true,
            contentPadding: EdgeInsets.symmetric(horizontal: s(10), vertical: s(10)),
            suffixIcon: IconButton(onPressed: onToggle, icon: Icon(visible ? Icons.visibility_off_outlined : Icons.visibility_outlined, size: s(18), color: const Color(0xFF244C81))),
            enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(s(6)), borderSide: const BorderSide(color: Color(0xFFC9D3E1))),
            focusedBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(s(6)), borderSide: const BorderSide(color: Color(0xFF086AF2))),
          ),
        ),
      ]),
    );
  }
}

class _PhoneInput extends StatelessWidget {
  const _PhoneInput({required this.label, required this.country, required this.controller, required this.scale, required this.onCountry});
  final String label;
  final String country;
  final TextEditingController controller;
  final double scale;
  final ValueChanged<String> onCountry;
  @override
  Widget build(BuildContext context) {
    double s(double value) => value * scale;
    return Padding(
      padding: EdgeInsets.only(bottom: s(9)),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        _FieldLabel(label, scale: scale),
        SizedBox(height: s(4)),
        Container(
          height: s(39),
          decoration: BoxDecoration(border: Border.all(color: const Color(0xFFC9D3E1)), borderRadius: BorderRadius.circular(s(6))),
          child: Row(children: [
            Padding(padding: EdgeInsets.only(left: s(7)), child: Text('🇨🇮', style: TextStyle(fontSize: s(15)))),
            DropdownButtonHideUnderline(
              child: DropdownButton<String>(
                value: country,
                dropdownColor: Colors.white,
                borderRadius: BorderRadius.circular(s(10)),
                elevation: 3,
                icon: Icon(Icons.keyboard_arrow_down_rounded, color: const Color(0xFF5A7196), size: s(18)),
                items: const ['+225', '+221', '+223', '+226', '+233', '+224']
                    .map((v) => DropdownMenuItem(value: v, child: Text(v, style: const TextStyle(color: Color(0xFF14284D)))))
                    .toList(),
                onChanged: (v) { if (v != null) onCountry(v); },
                style: TextStyle(color: const Color(0xFF14284D), fontSize: s(9.6)),
              ),
            ),
            Container(width: 1, height: s(25), color: const Color(0xFFD8E0EA)),
            Expanded(
              child: TextField(
                controller: controller,
                keyboardType: TextInputType.phone,
                inputFormatters: [FilteringTextInputFormatter.digitsOnly, _PhoneGroupFormatter()],
                style: TextStyle(color: const Color(0xFF14284D), fontSize: s(9.7)),
                decoration: InputDecoration(border: InputBorder.none, isDense: true, contentPadding: EdgeInsets.symmetric(horizontal: s(8)), hintText: '07 12 34 56 78', hintStyle: TextStyle(color: const Color(0xFF8797B0), fontSize: s(9.4))),
              ),
            ),
          ]),
        ),
      ]),
    );
  }
}

class _Dropdown<T> extends StatelessWidget {
  const _Dropdown({required this.label, required this.hint, required this.value, required this.items, required this.text, required this.scale, required this.onChanged});
  final String label;
  final String hint;
  final T? value;
  final List<T> items;
  final String Function(T) text;
  final double scale;
  final ValueChanged<T?>? onChanged;
  @override
  Widget build(BuildContext context) {
    double s(double value) => value * scale;
    final safeValue = value != null && items.contains(value) ? value : null;
    return Padding(
      padding: EdgeInsets.only(bottom: s(9)),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        _FieldLabel(label, scale: scale),
        SizedBox(height: s(4)),
        DropdownButtonFormField<T>(
          value: safeValue,
          dropdownColor: Colors.white,
          borderRadius: BorderRadius.circular(s(10)),
          elevation: 3,
          itemHeight: null,
            menuMaxHeight: s(320),
          icon: Icon(Icons.keyboard_arrow_down_rounded, color: const Color(0xFF5A7196), size: s(20)),
          items: items
              .map((item) => DropdownMenuItem<T>(
                    value: item,
                    child: Text(
                      text(item),
                      overflow: TextOverflow.ellipsis,
                      style: TextStyle(color: const Color(0xFF14284D), fontSize: s(9.7)),
                    ),
                  ))
              .toList(),
          onChanged: onChanged,
          isExpanded: true,
          style: TextStyle(color: const Color(0xFF14284D), fontSize: s(9.7)),
          decoration: InputDecoration(
            hintText: hint,
            hintStyle: TextStyle(color: const Color(0xFF8797B0), fontSize: s(9.3)),
            isDense: true,
            contentPadding: EdgeInsets.symmetric(horizontal: s(9), vertical: s(9)),
            enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(s(6)), borderSide: const BorderSide(color: Color(0xFFC9D3E1))),
            focusedBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(s(6)), borderSide: const BorderSide(color: Color(0xFF086AF2))),
          ),
        ),
      ]),
    );
  }
}

class _NotesInput extends StatelessWidget {
  const _NotesInput({required this.label, required this.controller, required this.hint, required this.scale, this.maxLength = 200});
  final String label;
  final TextEditingController controller;
  final String hint;
  final double scale;
  final int maxLength;
  @override
  Widget build(BuildContext context) {
    double s(double value) => value * scale;
    return Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      _FieldLabel(label, scale: scale),
      SizedBox(height: s(4)),
      TextField(
        controller: controller,
        maxLines: 3,
        maxLength: maxLength,
        style: TextStyle(color: const Color(0xFF14284D), fontSize: s(9.8)),
        decoration: InputDecoration(
          hintText: hint,
          hintStyle: TextStyle(color: const Color(0xFF8797B0), fontSize: s(9.2)),
          isDense: true,
          contentPadding: EdgeInsets.all(s(9)),
          enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(s(6)), borderSide: const BorderSide(color: Color(0xFFC9D3E1))),
          focusedBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(s(6)), borderSide: const BorderSide(color: Color(0xFF086AF2))),
        ),
      ),
    ]);
  }
}

class _Segmented extends StatelessWidget {
  const _Segmented({required this.scale, required this.leftLabel, required this.rightLabel, required this.rightSelected, required this.onChanged});
  final double scale;
  final String leftLabel;
  final String rightLabel;
  final bool rightSelected;
  final ValueChanged<bool> onChanged;
  @override
  Widget build(BuildContext context) {
    double s(double value) => value * scale;
    return Container(
      height: s(37),
      decoration: BoxDecoration(border: Border.all(color: const Color(0xFFC4D0E0)), borderRadius: BorderRadius.circular(s(6))),
      child: Row(children: [
        Expanded(
          child: InkWell(
            onTap: () => onChanged(false),
            child: Container(
              alignment: Alignment.center,
              decoration: BoxDecoration(
                color: !rightSelected ? const Color(0xFF075EF0) : Colors.white,
                borderRadius: BorderRadius.horizontal(left: Radius.circular(s(5))),
              ),
              child: Text(
                leftLabel,
                style: TextStyle(
                  color: !rightSelected ? Colors.white : const Color(0xFF17335F),
                  fontSize: s(9.8),
                ),
              ),
            ),
          ),
        ),
        Expanded(
          child: InkWell(
            onTap: () => onChanged(true),
            child: Container(
              alignment: Alignment.center,
              decoration: BoxDecoration(
                color: rightSelected ? const Color(0xFF075EF0) : Colors.white,
                borderRadius: BorderRadius.horizontal(right: Radius.circular(s(5))),
              ),
              child: Text(
                rightLabel,
                style: TextStyle(
                  color: rightSelected ? Colors.white : const Color(0xFF17335F),
                  fontSize: s(9.8),
                ),
              ),
            ),
          ),
        ),
      ]),
    );
  }
}

class _PhotoUpload extends StatelessWidget {
  const _PhotoUpload({required this.path, required this.scale, required this.icon, required this.onCamera, required this.onImport});
  final String? path;
  final double scale;
  final IconData icon;
  final VoidCallback onCamera;
  final VoidCallback onImport;
  @override
  Widget build(BuildContext context) {
    double s(double value) => value * scale;
    return Row(children: [
      Container(
        width: s(78),
        height: s(70),
        decoration: BoxDecoration(color: const Color(0xFFF0F4FA), borderRadius: BorderRadius.circular(s(7)), border: Border.all(color: const Color(0xFFC7D2E1))),
        clipBehavior: Clip.antiAlias,
        child: path != null ? Image.file(File(path!), fit: BoxFit.cover, errorBuilder: (_, __, ___) => Icon(icon, size: s(34), color: const Color(0xFF8BA1C3))) : Icon(icon, size: s(34), color: const Color(0xFF8BA1C3)),
      ),
      SizedBox(width: s(10)),
      Expanded(
        child: Container(
          constraints: BoxConstraints(minHeight: s(70)),
          padding: EdgeInsets.symmetric(horizontal: s(8), vertical: s(7)),
          decoration: BoxDecoration(
            border: Border.all(color: const Color(0xFFC5D1E1), style: BorderStyle.solid),
            borderRadius: BorderRadius.circular(s(7)),
          ),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Row(
                children: [
                  Expanded(
                    child: OutlinedButton.icon(
                      onPressed: onCamera,
                      icon: Icon(Icons.photo_camera_outlined, size: s(14)),
                      label: FittedBox(
                        fit: BoxFit.scaleDown,
                        child: Text('Prendre une photo', style: TextStyle(fontSize: s(8.2))),
                      ),
                      style: OutlinedButton.styleFrom(
                        foregroundColor: const Color(0xFF075EF0),
                        side: const BorderSide(color: Color(0xFF075EF0)),
                        minimumSize: Size.zero,
                        tapTargetSize: MaterialTapTargetSize.shrinkWrap,
                        visualDensity: VisualDensity.compact,
                        padding: EdgeInsets.symmetric(horizontal: s(4), vertical: s(5)),
                      ),
                    ),
                  ),
                  SizedBox(width: s(6)),
                  Expanded(
                    child: OutlinedButton.icon(
                      onPressed: onImport,
                      icon: Icon(Icons.cloud_upload_outlined, size: s(14)),
                      label: FittedBox(
                        fit: BoxFit.scaleDown,
                        child: Text('Importer', style: TextStyle(fontSize: s(8.2))),
                      ),
                      style: OutlinedButton.styleFrom(
                        foregroundColor: const Color(0xFF075EF0),
                        side: const BorderSide(color: Color(0xFF075EF0)),
                        minimumSize: Size.zero,
                        tapTargetSize: MaterialTapTargetSize.shrinkWrap,
                        visualDensity: VisualDensity.compact,
                        padding: EdgeInsets.symmetric(horizontal: s(4), vertical: s(5)),
                      ),
                    ),
                  ),
                ],
              ),
              SizedBox(height: s(3)),
              FittedBox(
                fit: BoxFit.scaleDown,
                child: Text(
                  'Formats acceptés : JPG, PNG – Max 5 Mo',
                  style: TextStyle(color: const Color(0xFF60789D), fontSize: s(7.2)),
                ),
              ),
            ],
          ),
        ),
      ),
    ]);
  }
}

class _IdentityUpload extends StatelessWidget {
  const _IdentityUpload({required this.title, required this.path, required this.scale, required this.onCamera, required this.onImport});
  final String title;
  final String? path;
  final double scale;
  final VoidCallback onCamera;
  final VoidCallback onImport;
  @override
  Widget build(BuildContext context) {
    double s(double value) => value * scale;
    return Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      _FieldLabel(title, scale: scale),
      SizedBox(height: s(5)),
      Container(
        height: s(112),
        padding: EdgeInsets.all(s(8)),
        decoration: BoxDecoration(border: Border.all(color: const Color(0xFF9FB2D0)), borderRadius: BorderRadius.circular(s(7)), color: const Color(0xFFFAFCFF)),
        child: Column(children: [
          Expanded(
            child: path != null
                ? ClipRRect(
                    borderRadius: BorderRadius.circular(s(5)),
                    child: Image.file(
                      File(path!),
                      fit: BoxFit.contain,
                      errorBuilder: (_, __, ___) => const Icon(
                        Icons.badge_outlined,
                        color: Color(0xFF8FA3C2),
                      ),
                    ),
                  )
                : Icon(
                    Icons.badge_outlined,
                    color: const Color(0xFF8FA3C2),
                    size: s(42),
                  ),
          ),
          Row(children: [
            Expanded(child: OutlinedButton.icon(onPressed: onCamera, icon: Icon(Icons.photo_camera_outlined, size: s(14)), label: Text('Photo', style: TextStyle(fontSize: s(7.8))), style: OutlinedButton.styleFrom(foregroundColor: const Color(0xFF075EF0), side: const BorderSide(color: Color(0xFF075EF0)), padding: EdgeInsets.zero))),
            SizedBox(width: s(5)),
            Expanded(child: OutlinedButton.icon(onPressed: onImport, icon: Icon(Icons.cloud_upload_outlined, size: s(14)), label: Text('Importer', style: TextStyle(fontSize: s(7.8))), style: OutlinedButton.styleFrom(foregroundColor: const Color(0xFF183B72), side: const BorderSide(color: Color(0xFFBBC7D9)), padding: EdgeInsets.zero))),
          ]),
        ]),
      ),
    ]);
  }
}

class _ChoiceCard extends StatelessWidget {
  const _ChoiceCard({required this.selected, required this.icon, required this.title, required this.subtitle, required this.scale, required this.onTap});
  final bool selected;
  final IconData icon;
  final String title;
  final String subtitle;
  final double scale;
  final VoidCallback onTap;
  @override
  Widget build(BuildContext context) {
    double s(double value) => value * scale;
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(s(7)),
      child: Container(
        constraints: BoxConstraints(minHeight: s(73)),
        padding: EdgeInsets.all(s(9)),
        decoration: BoxDecoration(borderRadius: BorderRadius.circular(s(7)), border: Border.all(color: selected ? const Color(0xFF075EF0) : const Color(0xFFC5D0DF), width: selected ? 1.2 : .8), color: Colors.white),
        child: Row(children: [
          Icon(selected ? Icons.radio_button_checked : Icons.radio_button_off, color: selected ? const Color(0xFF075EF0) : const Color(0xFF6D82A2), size: s(18)),
          SizedBox(width: s(6)),
          Icon(icon, color: selected ? const Color(0xFF075EF0) : const Color(0xFF6D82A2), size: s(25)),
          SizedBox(width: s(8)),
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text(title, style: TextStyle(color: selected ? const Color(0xFF075EF0) : const Color(0xFF14284D), fontSize: s(9.4), fontWeight: FontWeight.w700)), SizedBox(height: s(3)), Text(subtitle, style: TextStyle(color: const Color(0xFF526C93), fontSize: s(8.1), height: 1.25))])),
        ]),
      ),
    );
  }
}

class _PayoutOption extends StatelessWidget {
  const _PayoutOption({required this.value, required this.label, this.assetPath, this.icon, required this.selected, required this.scale, required this.onTap});
  final String value;
  final String label;
  final String? assetPath;
  final IconData? icon;
  final bool selected;
  final double scale;
  final VoidCallback onTap;
  @override
  Widget build(BuildContext context) {
    double s(double value) => value * scale;
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(s(7)),
      child: Container(
        height: s(76),
        padding: EdgeInsets.all(s(6)),
        decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(s(7)), border: Border.all(color: selected ? const Color(0xFF075EF0) : const Color(0xFFC8D2E0), width: selected ? 1.4 : 1)),
        child: Column(children: [
          Align(alignment: Alignment.topLeft, child: Icon(selected ? Icons.radio_button_checked : Icons.radio_button_off, color: selected ? const Color(0xFF075EF0) : const Color(0xFF8DA0BC), size: s(13))),
          Expanded(
            child: Center(
              child: assetPath != null
                  ? Image.asset(assetPath!, height: s(26), fit: BoxFit.contain)
                  : Icon(icon ?? Icons.account_balance_outlined, size: s(24), color: const Color(0xFF174E91)),
            ),
          ),
          Text(label, textAlign: TextAlign.center, maxLines: 2, overflow: TextOverflow.ellipsis, style: TextStyle(color: const Color(0xFF14284D), fontSize: s(6.9), height: 1.05)),
        ]),
      ),
    );
  }
}

class _Buttons extends StatelessWidget {
  const _Buttons({required this.previousLabel, required this.nextLabel, required this.scale, required this.onPrevious, required this.onNext, this.loading = false});
  final String previousLabel;
  final String nextLabel;
  final double scale;
  final VoidCallback onPrevious;
  final VoidCallback onNext;
  final bool loading;
  @override
  Widget build(BuildContext context) {
    double s(double value) => value * scale;
    return Row(children: [
      Expanded(child: OutlinedButton(onPressed: onPrevious, style: OutlinedButton.styleFrom(foregroundColor: const Color(0xFF075EF0), side: const BorderSide(color: Color(0xFF075EF0)), padding: EdgeInsets.symmetric(vertical: s(11)), shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(s(7)))), child: Text(previousLabel, style: TextStyle(fontSize: s(10.2), fontWeight: FontWeight.w600)))),
      SizedBox(width: s(10)),
      Expanded(child: FilledButton(onPressed: onNext, style: FilledButton.styleFrom(backgroundColor: OvanieColors.orange, foregroundColor: Colors.white, padding: EdgeInsets.symmetric(vertical: s(11)), shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(s(7)))), child: Row(mainAxisAlignment: MainAxisAlignment.center, children: [if (loading) ...[SizedBox(width: s(14), height: s(14), child: const CircularProgressIndicator(strokeWidth: 2, color: Colors.white)), SizedBox(width: s(8))], Text(nextLabel, style: TextStyle(fontSize: s(10.2), fontWeight: FontWeight.w600)), if (!loading) ...[SizedBox(width: s(8)), Icon(Icons.arrow_forward_rounded, size: s(17))]]))),
    ]);
  }
}

class _InlineError extends StatelessWidget {
  const _InlineError({required this.message, required this.scale});
  final String message;
  final double scale;
  @override
  Widget build(BuildContext context) => Container(
        padding: EdgeInsets.all(10 * scale),
        decoration: BoxDecoration(color: const Color(0xFFFFECEF), borderRadius: BorderRadius.circular(7 * scale)),
        child: Row(children: [const Icon(Icons.error_outline, color: Color(0xFFC2384D), size: 18), SizedBox(width: 7 * scale), Expanded(child: Text(message, style: TextStyle(color: const Color(0xFF9E2B3D), fontSize: 9 * scale)))]),
      );
}

class _InfoBar extends StatelessWidget {
  const _InfoBar({required this.step, required this.scale});
  final int step;
  final double scale;
  @override
  Widget build(BuildContext context) {
    final text = switch (step) {
      1 => 'Les informations du responsable seront associées au compte vendeur.',
      2 => 'Les informations de la boutique seront visibles dans l’espace vendeur.',
      3 => 'La position de la boutique servira au calcul logistique et au suivi terrain.',
      4 => 'Les documents d’identité serviront à la vérification du compte vendeur.',
      5 => 'Les informations de reversement serviront aux paiements du vendeur sur OVANIE.',
      _ => 'Ces informations seront utilisées pour créer le compte vendeur et ouvrir sa boutique.',
    };
    return Container(
      padding: EdgeInsets.symmetric(horizontal: 10 * scale, vertical: 8 * scale),
      decoration: BoxDecoration(color: const Color(0xFF0A2A58), borderRadius: BorderRadius.circular(7 * scale), border: Border.all(color: const Color(0xFF2C578A))),
      child: Row(children: [Icon(Icons.info_outline, color: Colors.white, size: 15 * scale), SizedBox(width: 7 * scale), Expanded(child: Text(text, style: TextStyle(color: Colors.white, fontSize: 8.8 * scale)))]),
    );
  }
}

class _RecapSection extends StatelessWidget {
  const _RecapSection({required this.title, required this.icon, required this.rows, required this.scale, required this.onEdit});
  final String title;
  final IconData icon;
  final List<(String, String)> rows;
  final double scale;
  final VoidCallback onEdit;
  @override
  Widget build(BuildContext context) {
    double s(double value) => value * scale;
    return Container(
      padding: EdgeInsets.all(s(10)),
      decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(s(7)), border: Border.all(color: const Color(0xFFD4DDE9))),
      child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Container(width: s(58), height: s(58), decoration: BoxDecoration(color: const Color(0xFFF0F4F9), borderRadius: BorderRadius.circular(s(6))), child: Icon(icon, size: s(31), color: const Color(0xFF7F9CC3))),
        SizedBox(width: s(10)),
        Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Row(children: [Expanded(child: Text(title, style: TextStyle(color: const Color(0xFF0B1D42), fontSize: s(11.3), fontWeight: FontWeight.w800))), TextButton.icon(onPressed: onEdit, icon: Icon(Icons.edit_outlined, size: s(13)), label: Text('Modifier', style: TextStyle(fontSize: s(8.7))), style: TextButton.styleFrom(foregroundColor: const Color(0xFF075EF0), padding: EdgeInsets.zero, minimumSize: Size.zero))]),
          for (final row in rows)
            Padding(
              padding: EdgeInsets.only(bottom: s(3)),
              child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [SizedBox(width: s(110), child: Text(row.$1, style: TextStyle(color: const Color(0xFF15345F), fontSize: s(8.2), fontWeight: FontWeight.w600))), Expanded(child: Text(row.$2.isEmpty ? '—' : row.$2, maxLines: row.$1 == 'Description' || row.$1 == 'Adresse complète' ? 3 : 1, overflow: TextOverflow.ellipsis, style: TextStyle(color: const Color(0xFF4D6890), fontSize: s(8.2), height: 1.2)))]),
            ),
        ])),
      ]),
    );
  }
}

class _PhoneGroupFormatter extends TextInputFormatter {
  @override
  TextEditingValue formatEditUpdate(TextEditingValue oldValue, TextEditingValue newValue) {
    final digits = newValue.text.replaceAll(RegExp(r'\D'), '');
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

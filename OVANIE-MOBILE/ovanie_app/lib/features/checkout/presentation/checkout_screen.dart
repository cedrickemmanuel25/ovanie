import 'dart:async';
import 'dart:math' as math;

import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_map/flutter_map.dart';
import 'package:latlong2/latlong.dart';

import '../../../app/theme.dart';
import '../../../core/network/api_client.dart';
import '../../../core/reference_data/reference_data_store.dart';
import '../../../core/platform/device_location.dart';
import '../../../core/widgets/in_app_payment_screen.dart';
import '../../../core/utils/formatters.dart';
import '../../../core/utils/text_cleaner.dart';
import '../../auth/domain/session_store.dart';
import '../../cart/data/cart_api_repository.dart';
import '../../cart/domain/cart_store.dart';
import '../../orders/data/orders_repository.dart';
import '../data/checkout_repository.dart';
import '../data/geo_repository.dart';
import '../domain/checkout_models.dart';
import 'order_confirmation_screen.dart';

class CheckoutScreen extends StatefulWidget {
  final List<int> selectedProductIds;

  const CheckoutScreen({
    super.key,
    this.selectedProductIds = const <int>[],
  });

  @override
  State<CheckoutScreen> createState() => _CheckoutScreenState();
}

class _CheckoutScreenState extends State<CheckoutScreen> {
  final _repository = const CheckoutRepository();
  final _geoRepository = const GeoRepository();

  late final Future<void> _cartSyncSettled;
  Object? _cartSyncError;
  late final TextEditingController _name;
  late final TextEditingController _phone;
  late final TextEditingController _communeController;
  late final TextEditingController _quartierController;
  late final TextEditingController _notesController;
  late final TextEditingController _addressController;
  final GlobalKey _manualAddressFormKey = GlobalKey();

  String _zone = 'abidjan';
  String _commune = '';
  String _quartier = '';
  String _city = '';
  String _address = '';
  String _notes = '';
  double? _latitude;
  double? _longitude;
  double? _geoAccuracyMeters;
  int? _savedAddressId;
  String _geoSource = 'mobile_manual';
  String _localityType = '';

  bool _addressConfirmed = false;
  bool _calculating = false;
  bool _placing = false;
  bool _loadingAddresses = true;
  bool _locating = false;
  String _paymentMethod = '';
  String _onlineOperator = '';
  String _paymentPhone = '';
  String _orangeOtp = '';
  String? _error;
  CheckoutPreview? _preview;
  List<CheckoutSavedAddress> _savedAddresses = const [];
  int _stage = 1;
  bool _customCommune = false;
  bool _customQuartier = false;

  Set<int> get _selectedProductIds => widget.selectedProductIds
      .where((id) => id > 0)
      .toSet();

  Iterable<CartLine> get _selectedCartLines {
    final selected = _selectedProductIds;
    if (selected.isEmpty) return CartStore.instance.lines;
    return CartStore.instance.lines.where(
      (line) => selected.contains(line.product.id),
    );
  }

  double get _selectedSubtotal => _selectedCartLines.fold<double>(
        0,
        (sum, line) => sum + line.subtotal,
      );

  int get _selectedItemsCount => _selectedCartLines.fold<int>(
        0,
        (sum, line) => sum + line.quantity,
      );

  static const String _customCommuneOption = 'Autre commune / ville';
  static const String _customQuartierOption = 'Autre quartier / zone';

  List<String> get _abidjanCommunes =>
      OvanieReferenceDataStore.instance.communeNames;

  Map<String, List<String>> get _quartiersByCommune =>
      OvanieReferenceDataStore.instance.quartersByCommuneName;

  List<String> get _communeOptions {
    final values = <String>{..._abidjanCommunes};
    if (_commune.trim().isNotEmpty) values.add(_commune.trim());
    for (final item in _savedAddresses) {
      if (item.commune.trim().isNotEmpty) values.add(item.commune.trim());
    }
    final list = values.toList()
      ..sort((a, b) => a.toLowerCase().compareTo(b.toLowerCase()));
    list.add(_customCommuneOption);
    return list;
  }

  List<String> get _quartierOptions {
    final values = <String>{};
    final commune = _commune.trim();
    if (commune.isNotEmpty) {
      values.addAll(_quartiersByCommune[commune] ?? const <String>[]);
      for (final item in _savedAddresses) {
        if (item.commune.trim().toLowerCase() == commune.toLowerCase()) {
          final value = item.sousQuartier.trim().isNotEmpty
              ? item.sousQuartier.trim()
              : item.quartier.trim();
          if (value.isNotEmpty) values.add(value);
        }
      }
    }
    if (_quartier.trim().isNotEmpty) values.add(_quartier.trim());
    final list = values.toList()
      ..sort((a, b) => a.toLowerCase().compareTo(b.toLowerCase()));
    list.add(_customQuartierOption);
    return list;
  }

  List<CheckoutPaymentOperatorOption> get _availableOnlineOperators {
    final apiOperators =
        _onlinePaymentOption?.operators ??
        const <CheckoutPaymentOperatorOption>[];
    if (apiOperators.isNotEmpty) return apiOperators;

    final references =
        OvanieReferenceDataStore.instance.options('checkout_operators');
    if (references.isNotEmpty) {
      return references
          .map(
            (item) => CheckoutPaymentOperatorOption(
              code: item.code,
              label: item.label,
            ),
          )
          .toList(growable: false);
    }

    return const <CheckoutPaymentOperatorOption>[];
  }

  @override
  void initState() {
    super.initState();
    _name = TextEditingController(text: SessionStore.instance.name ?? '');
    _phone = TextEditingController(
      text: formatCiPhoneDisplay(SessionStore.instance.phone ?? ''),
    );
    _communeController = TextEditingController();
    _quartierController = TextEditingController();
    _notesController = TextEditingController();
    _addressController = TextEditingController();
    final sync = const CartApiRepository().mergeLocalCartIntoServer(
      refreshLocal: false,
    );
    _cartSyncSettled = sync.then<void>(
      (_) {},
      onError: (Object error, StackTrace stackTrace) {
        _cartSyncError = error;
      },
    );
    unawaited(_loadSavedAddresses(selectDefault: true));
  }

  @override
  void dispose() {
    _name.dispose();
    _phone.dispose();
    _communeController.dispose();
    _quartierController.dispose();
    _notesController.dispose();
    _addressController.dispose();
    super.dispose();
  }

  void _syncManualAddressControllers() {
    if (!_customCommune && _communeController.text != _commune) {
      _communeController.text = _commune;
    }
    if (!_customQuartier && _quartierController.text != _quartier) {
      _quartierController.text = _quartier;
    }
    if (_notesController.text != _notes) _notesController.text = _notes;
    if (_addressController.text != _address) _addressController.text = _address;
  }

  Future<void> _beginNewAddressEntry() async {
    FocusManager.instance.primaryFocus?.unfocus();

    if (_savedAddressId != null) {
      setState(() {
        _savedAddressId = null;
        _addressConfirmed = false;
        _zone = 'abidjan';
        _commune = '';
        _quartier = '';
        _city = '';
        _address = '';
        _notes = '';
        _latitude = null;
        _longitude = null;
        _geoAccuracyMeters = null;
        _geoSource = 'mobile_manual';
        _localityType = '';
        _customCommune = false;
        _customQuartier = false;
        _preview = null;
        _error = null;
      });
      _communeController.clear();
      _quartierController.clear();
      _notesController.clear();
      _addressController.clear();
    } else if (mounted) {
      // Ce bouton sert uniquement à préparer le formulaire. Il ne déclenche
      // jamais la localisation et ne doit pas réafficher une ancienne erreur GPS.
      setState(() => _error = null);
    }

    await _scrollToManualAddressForm();
  }

  void _selectCommune(String? value) {
    if (value == null) return;

    if (value == _customCommuneOption) {
      setState(() {
        _customCommune = true;
        _commune = '';
        _quartier = '';
        _customQuartier = false;
        _savedAddressId = null;
        _addressConfirmed = false;
        _preview = null;
        _geoSource = 'mobile_manual';
      });
      _communeController.clear();
      _quartierController.clear();
      return;
    }

    final communeChanged =
        _commune.trim().toLowerCase() != value.trim().toLowerCase();
    setState(() {
      _customCommune = false;
      _commune = value;
      _city = 'Abidjan';
      _zone = 'abidjan';
      _savedAddressId = null;
      _preview = null;
      _geoSource = 'mobile_manual';
      if (communeChanged) {
        _quartier = '';
        _customQuartier = false;
        _quartierController.clear();
      }
    });
    _communeController.text = value;
    _markManualAddress();
  }

  void _selectQuartier(String? value) {
    if (value == null) return;

    if (value == _customQuartierOption) {
      setState(() {
        _customQuartier = true;
        _quartier = '';
        _savedAddressId = null;
        _addressConfirmed = false;
        _preview = null;
        _geoSource = 'mobile_manual';
      });
      _quartierController.clear();
      return;
    }

    setState(() {
      _customQuartier = false;
      _quartier = value;
      _savedAddressId = null;
      _preview = null;
      _geoSource = 'mobile_manual';
    });
    _quartierController.text = value;
    _markManualAddress();
  }

  Future<void> _scrollToManualAddressForm() async {
    FocusManager.instance.primaryFocus?.unfocus();
    if (mounted && _error != null) {
      setState(() => _error = null);
    }
    await Future<void>.delayed(const Duration(milliseconds: 40));
    final context = _manualAddressFormKey.currentContext;
    if (context == null) return;
    await Scrollable.ensureVisible(
      context,
      duration: const Duration(milliseconds: 360),
      curve: Curves.easeOutCubic,
      alignment: 0.06,
    );
  }

  Future<void> _loadSavedAddresses({bool selectDefault = false}) async {
    try {
      final addresses = await _repository.savedAddresses();
      if (!mounted) return;
      CheckoutSavedAddress? defaultAddress;
      for (final address in addresses) {
        if (address.isDefault) {
          defaultAddress = address;
          break;
        }
      }
      setState(() {
        _savedAddresses = addresses;
        _loadingAddresses = false;
      });
      if (selectDefault && !_addressConfirmed && defaultAddress != null) {
        _applySavedAddress(defaultAddress, refresh: true);
      }
    } catch (_) {
      if (!mounted) return;
      setState(() => _loadingAddresses = false);
    }
  }

  CheckoutAddress _payload({String? paymentMethod}) {
    return CheckoutAddress(
      fullName: _name.text,
      phone: normalizeCiPhoneForApi(_phone.text),
      zone: _zone,
      commune: _commune,
      quartier: _quartier,
      city: _city,
      address: _address,
      paymentMethod: paymentMethod ?? _paymentMethod,
      notes: _notes,
      latitude: _latitude,
      longitude: _longitude,
      geoAccuracyMeters: _geoAccuracyMeters,
      savedAddressId: _savedAddressId,
      geoSource: _geoSource,
      localityType: _localityType,
      onlineOperator: _onlineOperator,
      paymentPhone: _paymentPhone,
      orangeOtp: _orangeOtp,
      checkoutProductIds: _selectedProductIds.toList(growable: false),
    );
  }

  Future<void> _ensureCartReady() async {
    await _cartSyncSettled;
    final syncError = _cartSyncError;
    if (syncError != null) throw syncError;
    if (CartStore.instance.lines.isEmpty || _selectedCartLines.isEmpty) {
      throw const OvanieApiException('Votre sélection de panier OVANIE est vide.');
    }
  }

  void _applySavedAddress(CheckoutSavedAddress item, {bool refresh = true}) {
    _name.text = item.recipientName.trim().isNotEmpty
        ? item.recipientName.trim()
        : _name.text;
    _phone.text = formatCiPhoneDisplay(
      item.phone.trim().isNotEmpty ? item.phone : _phone.text,
    );
    final isAbidjan =
        item.city.toLowerCase().contains('abidjan') ||
        item.commune.trim().isNotEmpty;
    setState(() {
      _savedAddressId = item.id;
      _zone = isAbidjan ? 'abidjan' : 'interieur';
      _city = item.city;
      _commune = item.commune;
      _quartier = item.sousQuartier.trim().isNotEmpty
          ? item.sousQuartier
          : item.quartier;
      _address = item.address;
      _latitude = item.latitude;
      _longitude = item.longitude;
      _localityType = item.localityType;
      _customCommune = false;
      _customQuartier = false;
      _geoSource = item.latitude != null && item.longitude != null
          ? 'saved_address'
          : 'mobile_manual';
      _geoAccuracyMeters = null;
      _addressConfirmed = true;
      _preview = null;
      _error = null;
    });
    _syncManualAddressControllers();
    if (refresh) unawaited(_refreshDeliveryPreview());
  }

  Future<void> _openAddressSheet() async {
    await _loadSavedAddresses();
    if (!mounted) return;

    final result = await showModalBottomSheet<_AddressFormResult>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      backgroundColor: Colors.transparent,
      builder: (_) => _AddressSheet(
        initial: _AddressFormResult(
          name: _name.text,
          phone: _phone.text,
          zone: _zone,
          commune: _commune,
          quartier: _quartier,
          city: _city,
          address: _address,
          notes: _notes,
          latitude: _latitude,
          longitude: _longitude,
          geoAccuracyMeters: _geoAccuracyMeters,
          savedAddressId: _savedAddressId,
          geoSource: _geoSource,
          localityType: _localityType,
        ),
        savedAddresses: _savedAddresses,
        loadingSavedAddresses: _loadingAddresses,
      ),
    );

    if (!mounted || result == null) return;
    _name.text = result.name;
    _phone.text = formatCiPhoneDisplay(result.phone);
    setState(() {
      _zone = result.zone;
      _commune = result.commune;
      _quartier = result.quartier;
      _city = result.city;
      _address = result.address;
      _notes = result.notes;
      _latitude = result.latitude;
      _longitude = result.longitude;
      _geoAccuracyMeters = result.geoAccuracyMeters;
      _savedAddressId = result.savedAddressId;
      _geoSource = result.geoSource;
      _localityType = result.localityType;
      _addressConfirmed = true;
      _preview = null;
      _error = null;
    });
    _syncManualAddressControllers();
    unawaited(_refreshDeliveryPreview());
  }

  bool _isLocationPermissionProblem(String message) {
    final value = message.toLowerCase();
    return value.contains('autorisation') ||
        value.contains('localisation refusée') ||
        value.contains('localisation refusee') ||
        value.contains('position précise') ||
        value.contains('position precise') ||
        value.contains('bloquée') ||
        value.contains('bloquee');
  }

  Future<bool> _showLocationPermissionHelp(
    DeviceLocationException error,
  ) async {
    if (!mounted) return false;
    final openDeviceSettings =
        error.reason == DeviceLocationFailureReason.serviceDisabled;
    final openAppSettings =
        error.reason == DeviceLocationFailureReason.permissionDeniedForever;
    final retry = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(18)),
        title: const Row(
          children: [
            Icon(Icons.location_on_outlined, color: OvanieColors.orange),
            SizedBox(width: 10),
            Expanded(
              child: Text(
                'Autoriser la localisation',
                style: TextStyle(
                  color: OvanieColors.navy,
                  fontWeight: FontWeight.w900,
                  fontSize: 18,
                ),
              ),
            ),
          ],
        ),
        content: Text(
          openDeviceSettings
              ? 'La localisation du téléphone est désactivée. Activez-la puis revenez dans OVANIE.'
              : 'OVANIE a besoin de votre position uniquement pour renseigner votre adresse de livraison. Activez « Localisation » et « Position précise », puis revenez dans l’application.',
          style: const TextStyle(
            color: Color(0xFF53617B),
            height: 1.45,
            fontSize: 13,
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(dialogContext).pop(false),
            child: const Text('Plus tard'),
          ),
          FilledButton(
            onPressed: () async {
              Navigator.of(
                dialogContext,
              ).pop(!openDeviceSettings && !openAppSettings);
              if (openDeviceSettings) {
                await DeviceLocation.openDeviceLocationSettings();
              } else if (openAppSettings) {
                await DeviceLocation.openAppLocationSettings();
              }
            },
            style: FilledButton.styleFrom(
              backgroundColor: OvanieColors.orange,
              foregroundColor: Colors.white,
            ),
            child: Text(
              openDeviceSettings || openAppSettings
                  ? 'Ouvrir les paramètres'
                  : 'Réessayer',
            ),
          ),
        ],
      ),
    );
    return retry == true;
  }

  Future<void> _editDeliveryPhone() async {
    final value = await showModalBottomSheet<String>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      backgroundColor: Colors.transparent,
      builder: (_) => _DeliveryEditSheet(
        title: 'Téléphone de livraison',
        label: 'Numéro de téléphone',
        initialValue: _phone.text,
        hint: '07 00 00 00 00',
        phone: true,
      ),
    );
    if (!mounted || value == null) return;
    _phone.text = formatCiPhoneDisplay(value);
    if (_paymentPhone.trim().isEmpty) {
      _paymentPhone = _phone.text;
    }
    setState(() => _error = null);
  }

  Future<void> _editDeliveryNote() async {
    final value = await showModalBottomSheet<String>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      backgroundColor: Colors.transparent,
      builder: (_) => _DeliveryEditSheet(
        title: 'Consigne au livreur',
        label: 'Indication de livraison',
        initialValue: _notes,
        hint: 'Ex. Appeler avant d’arriver, portail bleu…',
        phone: false,
        allowEmpty: true,
      ),
    );
    if (!mounted || value == null) return;
    setState(() {
      _notes = value.trim();
      _error = null;
    });
    _notesController.text = _notes;
  }

  Future<void> _useCurrentPosition() async {
    if (_locating) return;
    FocusManager.instance.primaryFocus?.unfocus();
    setState(() {
      _locating = true;
      _error = null;
    });
    try {
      var position = await DeviceLocation.currentPosition();

      // Sur un vrai téléphone, on laisse quelques secondes au fournisseur
      // Fused/GNSS pour améliorer la précision. Si Android ne descend pas sous
      // 30 m mais fournit tout de même un point réel exploitable, on conserve
      // ce meilleur point au lieu de bloquer complètement le checkout.
      if (!position.isEmulator && !position.hasGoodDeliveryAccuracy) {
        try {
          final precise =
              await DeviceLocation.positionStream(distanceFilterMeters: 0)
                  .firstWhere((candidate) => candidate.hasGoodDeliveryAccuracy)
                  .timeout(const Duration(seconds: 8));
          position = precise;
        } catch (_) {
          final accuracy = position.accuracy;
          if (accuracy == null ||
              !accuracy.isFinite ||
              accuracy <= 0 ||
              accuracy > 250) {
            throw const DeviceLocationException(
              'La position est encore trop imprécise. Activez « Position précise », placez-vous près d’une fenêtre ou à l’extérieur, puis réessayez.',
            );
          }
        }
      }

      final place = await _geoRepository.reverse(
        latitude: position.latitude,
        longitude: position.longitude,
        fresh: true,
      );
      if (!mounted) return;
      setState(() {
        _zone = place.zone;
        _commune = place.commune;
        _quartier = place.quartier;
        _city = place.city;
        _notes = place.landmark;
        _address = place.displayName;
        _latitude = position.latitude;
        _longitude = position.longitude;
        _geoAccuracyMeters = position.accuracy;
        _savedAddressId = null;
        _geoSource = place.source.isEmpty ? 'mobile_gps' : place.source;
        _localityType = place.localityType;
        _customCommune = false;
        _customQuartier = false;
        _addressConfirmed = true;
        _preview = null;
      });
      _syncManualAddressControllers();
      await _refreshDeliveryPreview();
      if (mounted) {
        await _scrollToManualAddressForm();
      }
    } on DeviceLocationException catch (error) {
      if (!mounted) return;
      if (_isLocationPermissionProblem(error.message) ||
          error.message.toLowerCase().contains(
            'activez la localisation du téléphone',
          )) {
        setState(() => _error = null);
        final retry = await _showLocationPermissionHelp(error);
        if (retry && mounted) {
          // Laisse le dialogue se fermer avant de relancer la demande Android.
          WidgetsBinding.instance.addPostFrameCallback((_) {
            if (mounted) unawaited(_useCurrentPosition());
          });
        }
      } else {
        setState(() => _error = error.message);
      }
    } catch (error) {
      if (!mounted) return;
      setState(() => _error = ApiClient.friendlyError(error));
    } finally {
      if (mounted) setState(() => _locating = false);
    }
  }

  Future<void> _refreshDeliveryPreview() async {
    if (!_addressConfirmed || _calculating) return;
    setState(() {
      _calculating = true;
      _error = null;
    });
    try {
      await _ensureCartReady();
      final payload = _payload();
      var preview = await _repository.preview(payload);
      final serverSaysEmpty =
          preview.subtotal <= 0 &&
          preview.deliveryMessage.toLowerCase().contains('panier est vide');
      if (serverSaysEmpty && CartStore.instance.lines.isNotEmpty) {
        await const CartApiRepository().mergeLocalCartIntoServer(
          refreshLocal: false,
        );
        preview = await _repository.preview(payload);
      }
      if (preview.subtotal <= 0 &&
          preview.deliveryMessage.toLowerCase().contains('panier est vide') &&
          CartStore.instance.lines.isNotEmpty) {
        throw const OvanieApiException(
          'Votre panier est bien conservé sur ce téléphone, mais sa synchronisation avec OVANIE n’est pas encore terminée. Réessayez dans quelques secondes.',
        );
      }
      if (!mounted) return;
      setState(() {
        if (preview.deliveryZone.isNotEmpty) _zone = preview.deliveryZone;
        if (preview.deliveryCommune.isNotEmpty)
          _commune = preview.deliveryCommune;
        if (preview.deliveryQuartier.isNotEmpty)
          _quartier = preview.deliveryQuartier;
        if (preview.deliveryCity.isNotEmpty) _city = preview.deliveryCity;
        if (preview.deliveryAddress.isNotEmpty)
          _address = preview.deliveryAddress;
        if (preview.deliveryLocalityType.isNotEmpty) {
          _localityType = preview.deliveryLocalityType;
        }
        final online = preview.paymentOptions.byCode('paydunya');
        final cod = preview.paymentOptions.byCode('cash_on_delivery');
        if (preview.paymentOptions.cashOnDeliveryRequired) {
          _paymentMethod = 'cash_on_delivery';
          _onlineOperator = '';
          _paymentPhone = '';
          _orangeOtp = '';
        } else if (_paymentMethod == 'paydunya' &&
            online != null &&
            !online.enabled) {
          _paymentMethod = '';
          _onlineOperator = '';
        } else if (_paymentMethod == 'cash_on_delivery' &&
            cod != null &&
            !cod.enabled) {
          _paymentMethod = '';
        }
        _preview = preview;
        if (_stage == 1 &&
            preview.validationState.addressValid &&
            preview.validationState.contactValid &&
            preview.validationState.deliveryValid) {
          _stage = 2;
        }
      });
      _syncManualAddressControllers();
    } catch (error) {
      if (!mounted) return;
      setState(() {
        _preview = null;
        _error = ApiClient.friendlyError(error);
      });
    } finally {
      if (mounted) setState(() => _calculating = false);
    }
  }

  bool get _deliveryReady {
    final preview = _preview;
    return preview != null &&
        preview.deliveryCalculated &&
        preview.deliveryAvailable &&
        !preview.deliveryQuoteRequired;
  }

  CheckoutPaymentMethodOption? get _onlinePaymentOption =>
      _preview?.paymentOptions.byCode('paydunya');
  CheckoutPaymentMethodOption? get _cashOnDeliveryOption =>
      _preview?.paymentOptions.byCode('cash_on_delivery');
  bool get _onlinePaymentEnabled => _onlinePaymentOption?.enabled ?? true;
  bool get _cashOnDeliveryEnabled => _cashOnDeliveryOption?.enabled ?? true;

  bool get _paymentSelectionReady {
    if (_paymentMethod == 'cash_on_delivery') {
      return _cashOnDeliveryEnabled;
    }
    if (_paymentMethod != 'paydunya' || !_onlinePaymentEnabled) {
      return false;
    }
    if (_onlineOperator.isEmpty) return false;
    if (_onlineOperator == 'card') return true;
    return ciLocalPhoneDigits(_paymentPhone).length == 10;
  }

  Future<void> _continueFromAddress() async {
    if (!_addressConfirmed) {
      if (_address.trim().isNotEmpty &&
          _commune.trim().isNotEmpty &&
          _quartier.trim().isNotEmpty &&
          _name.text.trim().isNotEmpty &&
          _phone.text.trim().isNotEmpty) {
        setState(() => _addressConfirmed = true);
      } else {
        setState(() {
          _error = 'Renseignez votre adresse de livraison avant de continuer.';
        });
        await _scrollToManualAddressForm();
        return;
      }
    }

    if (!_deliveryReady) await _refreshDeliveryPreview();
    if (!mounted || !_deliveryReady) return;

    setState(() {
      _stage = 2;
      _error = null;
    });
  }

  Future<void> _continueFromContact() async {
    if (!_deliveryReady) {
      await _refreshDeliveryPreview();
      if (!mounted || !_deliveryReady) return;
    }

    // Le système valide automatiquement adresse, contact et livraison.
    // Le moyen de paiement reste toujours un choix explicite du client.
    setState(() {
      _stage = 2;
      _error = null;
    });
  }

  void _selectCashOnDelivery() {
    if (!_cashOnDeliveryEnabled) return;
    setState(() {
      _paymentMethod = 'cash_on_delivery';
      _onlineOperator = '';
      _paymentPhone = '';
      _orangeOtp = '';
      _error = null;
    });
  }

  void _selectOnlinePayment() {
    if (!_onlinePaymentEnabled) return;

    final operators = _availableOnlineOperators;
    if (operators.isEmpty) {
      setState(() {
        _error =
            'Les moyens de paiement OVANIE sont momentanément indisponibles. Réessayez après actualisation.';
      });
      return;
    }
    final currentOperatorIsValid = _onlineOperator.isNotEmpty &&
        operators.any((item) => item.code == _onlineOperator);

    setState(() {
      _paymentMethod = 'paydunya';
      if (!currentOperatorIsValid) {
        _onlineOperator = '';
        _paymentPhone = '';
      }
      _orangeOtp = '';
      _error = null;
    });
  }

  void _selectOnlineOperator(CheckoutPaymentOperatorOption operator) {
    if (!_onlinePaymentEnabled) return;

    setState(() {
      _paymentMethod = 'paydunya';
      _onlineOperator = operator.code;
      _paymentPhone = operator.code == 'card'
          ? ''
          : normalizeCiPhoneForApi(_phone.text);
      _orangeOtp = '';
      _error = null;
    });
  }

  Future<void> _placeOrder() async {
    if (_placing) return;
    if (!SessionStore.instance.isAuthenticated) {
      setState(
        () => _error =
            'Votre session a expiré. Reconnectez-vous avant de commander.',
      );
      return;
    }
    if (!_addressConfirmed) {
      setState(() => _stage = 1);
      return;
    }
    if (!_deliveryReady) {
      await _refreshDeliveryPreview();
      if (!_deliveryReady) return;
    }
    if (_paymentMethod.isEmpty) {
      setState(() {
        _stage = 2;
        _error = 'Choisissez un mode de paiement avant de continuer.';
      });
      return;
    }
    if (_paymentMethod == 'paydunya' && _onlineOperator.isEmpty) {
      setState(() {
        _stage = 2;
        _error =
            'Choisissez Wave, Orange Money, MTN MoMo, Moov Money ou carte bancaire.';
      });
      return;
    }
    if (_paymentMethod == 'paydunya' && _onlineOperator != 'card') {
      final paymentDigits = ciLocalPhoneDigits(_paymentPhone);
      if (paymentDigits.length != 10) {
        setState(() {
          _stage = 2;
          _error =
              'Saisissez le numéro Mobile Money ivoirien à 10 chiffres qui sera débité.';
        });
        return;
      }
      _paymentPhone = normalizeCiPhoneForApi(_paymentPhone);
    }
    if (_paymentMethod == 'paydunya' && !_onlinePaymentEnabled) {
      setState(
        () => _error = _onlinePaymentOption?.reason.isNotEmpty == true
            ? _onlinePaymentOption!.reason
            : 'Le paiement en ligne n’est pas disponible pour cette commande.',
      );
      return;
    }
    if (_paymentMethod == 'cash_on_delivery' && !_cashOnDeliveryEnabled) {
      setState(
        () => _error = _cashOnDeliveryOption?.reason.isNotEmpty == true
            ? _cashOnDeliveryOption!.reason
            : 'Le paiement à la livraison n’est pas disponible pour cette commande.',
      );
      return;
    }

    setState(() {
      _placing = true;
      _error = null;
    });
    try {
      await _ensureCartReady();
      final result = await _repository.placeOrder(_payload());
      if (!mounted) return;
      if (_paymentMethod != 'paydunya') {
        await CartStore.instance.clearLocalMirrorOnly();
        try {
          await const CartApiRepository().refreshLocalCartFromServer();
        } catch (_) {}
        if (!mounted) return;
        await Navigator.of(context).pushReplacement(
          MaterialPageRoute<void>(
            builder: (_) => OrderConfirmationScreen(
              orderId: result.orderId,
              initialOrderNumber: result.orderNumber,
              initialAmount: result.paymentAmount,
              initialPaymentMethod: 'Paiement à la livraison',
              returnState: 'confirmed',
            ),
          ),
        );
        return;
      }

      var paymentUrl = result.paymentUrl.trim();
      if (paymentUrl.isEmpty) {
        final online = await _repository.startOnlinePayment(
          orderId: result.orderId,
          operator: _onlineOperator.isEmpty ? 'wave' : _onlineOperator,
          phone: _onlineOperator == 'card' ? '' : _paymentPhone,
          orangeOtp: _orangeOtp,
        );
        paymentUrl = online.paymentUrl.trim();
      }
      if (!mounted) return;
      if (paymentUrl.isNotEmpty) {
        await InAppPaymentScreen.open(context, paymentUrl);
      }
      if (!mounted) return;
      await Navigator.of(context).pushReplacement(
        MaterialPageRoute<void>(
          builder: (_) => _PaymentWaitingScreen(
            orderId: result.orderId,
            orderNumber: result.orderNumber,
            amount: result.paymentAmount,
            methodLabel: _paymentLabel,
            paymentUrl: paymentUrl,
          ),
        ),
      );
    } catch (error) {
      if (!mounted) return;
      setState(() => _error = ApiClient.friendlyError(error));
    } finally {
      if (mounted) setState(() => _placing = false);
    }
  }

  String get _paymentLabel {
    if (_paymentMethod == 'cash_on_delivery') return 'Paiement à la livraison';
    switch (_onlineOperator) {
      case 'wave':
        return 'Wave';
      case 'orange':
        return 'Orange Money';
      case 'mtn':
        return 'MTN MoMo';
      case 'moov':
        return 'Moov Money';
      case 'card':
        return 'Carte bancaire';
      default:
        return _paymentMethod == 'paydunya' ? 'Paiement en ligne' : 'Choisir';
    }
  }

  String get _addressTitle {
    if (_savedAddressId != null) {
      for (final item in _savedAddresses) {
        if (item.id == _savedAddressId && item.label.trim().isNotEmpty)
          return item.label.trim();
      }
    }
    if (_quartier.trim().isNotEmpty) return _quartier.trim();
    if (_commune.trim().isNotEmpty) return _commune.trim();
    if (_city.trim().isNotEmpty) return _city.trim();
    return 'Adresse de livraison';
  }

  @override
  Widget build(BuildContext context) {
    final amount =
        _preview?.total ?? _preview?.subtotal ?? _selectedSubtotal;

    final body = _stage == 1 ? _buildAddressStage() : _buildPaymentStage();

    final Widget bottomBar;
    if (_stage == 1) {
      bottomBar = _CheckoutBottomBar(
        amount: amount,
        loading: _calculating,
        label: 'Vérifier la livraison',
        onPressed: _continueFromAddress,
      );
    } else {
      bottomBar = _PaymentStageBottomBar(
        loading: _placing,
        label: _paymentMethod == 'cash_on_delivery'
            ? 'Confirmer la commande'
            : (_paymentMethod == 'paydunya' && _onlineOperator.isNotEmpty
                ? 'Payer maintenant'
                : 'Choisir un mode de paiement'),
        onPressed: _paymentSelectionReady ? _placeOrder : null,
      );
    }

    return Scaffold(
      backgroundColor: Colors.white,
      body: SafeArea(child: body),
      bottomNavigationBar: SafeArea(top: false, child: bottomBar),
    );
  }

  Widget _buildAddressStage() {
    return CustomScrollView(
      slivers: [
        SliverToBoxAdapter(
          child: _CheckoutPageHeader(
            current: 1,
            onBack: () => Navigator.of(context).maybePop(),
          ),
        ),
        SliverPadding(
          padding: const EdgeInsets.fromLTRB(14, 4, 14, 22),
          sliver: SliverList(
            delegate: SliverChildListDelegate([
              _CheckoutPanel(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text(
                      'Adresse de livraison',
                      style: _CheckoutStyles.panelTitle,
                    ),
                    const SizedBox(height: 10),
                    Row(
                      children: [
                        Expanded(
                          child: _SearchLikeField(
                            label:
                                'Rechercher une adresse ou choisir une adresse enregistrée',
                            onTap: _openAddressSheet,
                          ),
                        ),
                        const SizedBox(width: 9),
                        SizedBox(
                          height: 45,
                          child: OutlinedButton.icon(
                            onPressed: _locating ? null : _useCurrentPosition,
                            style: OutlinedButton.styleFrom(
                              foregroundColor: OvanieColors.navy,
                              side: const BorderSide(color: Color(0xFFD7DEE9)),
                              shape: RoundedRectangleBorder(
                                borderRadius: BorderRadius.circular(8),
                              ),
                            ),
                            icon: _locating
                                ? const SizedBox.square(
                                    dimension: 17,
                                    child: CircularProgressIndicator(
                                      strokeWidth: 2,
                                    ),
                                  )
                                : const Icon(
                                    Icons.my_location_rounded,
                                    size: 20,
                                  ),
                            label: const Text(
                              'Ma position',
                              style: TextStyle(fontWeight: FontWeight.w800),
                            ),
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 10),
                    _CheckoutMapPreview(
                      latitude: _latitude,
                      longitude: _longitude,
                    ),
                    const SizedBox(height: 10),
                    const Text(
                      'Adresses enregistrées',
                      style: _CheckoutStyles.subTitle,
                    ),
                    const SizedBox(height: 7),
                    if (_loadingAddresses)
                      const LinearProgressIndicator(minHeight: 2)
                    else if (_savedAddresses.isEmpty)
                      _EmptySavedAddresses(onTap: _beginNewAddressEntry)
                    else
                      ..._savedAddresses
                          .take(4)
                          .map(
                            (item) => Padding(
                              padding: const EdgeInsets.only(bottom: 6),
                              child: _SavedAddressTile(
                                item: item,
                                selected: item.id == _savedAddressId,
                                onTap: () => _applySavedAddress(item),
                              ),
                            ),
                          ),
                    InkWell(
                      onTap: _beginNewAddressEntry,
                      borderRadius: BorderRadius.circular(8),
                      child: Container(
                        height: 39,
                        alignment: Alignment.center,
                        decoration: BoxDecoration(
                          borderRadius: BorderRadius.circular(8),
                          border: Border.all(
                            color: const Color(0xFFD5DDE8),
                            style: BorderStyle.solid,
                          ),
                        ),
                        child: const Row(
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: [
                            Icon(
                              Icons.add_circle_outline_rounded,
                              color: OvanieColors.orange,
                              size: 20,
                            ),
                            SizedBox(width: 7),
                            Text(
                              'Ajouter une nouvelle adresse',
                              style: TextStyle(
                                color: OvanieColors.orange,
                                fontSize: 11.5,
                                fontWeight: FontWeight.w800,
                              ),
                            ),
                          ],
                        ),
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 8),
              _CheckoutPanel(
                key: _manualAddressFormKey,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text(
                      'Ajouter une nouvelle adresse',
                      style: _CheckoutStyles.subTitle,
                    ),
                    const SizedBox(height: 8),
                    Row(
                      children: [
                        Expanded(
                          child: _CompactTextField(
                            controller: _name,
                            label: 'Nom du destinataire',
                            onChanged: (_) => _markManualAddress(),
                          ),
                        ),
                        const SizedBox(width: 8),
                        Expanded(
                          child: _CompactTextField(
                            controller: _phone,
                            label: 'Téléphone',
                            keyboardType: TextInputType.phone,
                            prefixIcon: Icons.phone_outlined,
                            inputFormatters: const [CiPhoneInputFormatter()],
                            onChanged: (_) => _markManualAddress(),
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 7),
                    Row(
                      children: [
                        Expanded(
                          child: _CompactLocationDropdown(
                            label: 'Commune',
                            hint: 'Choisir',
                            value: _customCommune || _commune.trim().isEmpty
                                ? null
                                : _commune,
                            items: _communeOptions,
                            onChanged: _selectCommune,
                          ),
                        ),
                        const SizedBox(width: 8),
                        Expanded(
                          child: _CompactLocationDropdown(
                            label: 'Quartier',
                            hint: _commune.trim().isEmpty
                                ? 'Commune d’abord'
                                : 'Choisir',
                            value: _customQuartier || _quartier.trim().isEmpty
                                ? null
                                : _quartier,
                            items: _quartierOptions,
                            onChanged: _commune.trim().isEmpty
                                ? null
                                : _selectQuartier,
                          ),
                        ),
                      ],
                    ),
                    if (_customCommune) ...[
                      const SizedBox(height: 7),
                      _CompactEditableLocation(
                        controller: _communeController,
                        label: 'Commune / ville',
                        hint: 'Saisir la commune ou la ville',
                        onChanged: (value) {
                          setState(() {
                            _commune = value;
                            final isAbidjanCommune = _abidjanCommunes.any(
                              (item) =>
                                  item.toLowerCase() ==
                                  value.trim().toLowerCase(),
                            );
                            _zone = isAbidjanCommune ? 'abidjan' : 'interieur';
                            _city = isAbidjanCommune ? 'Abidjan' : value.trim();
                          });
                          _markManualAddress();
                        },
                      ),
                    ],
                    if (_customQuartier) ...[
                      const SizedBox(height: 7),
                      _CompactEditableLocation(
                        controller: _quartierController,
                        label: 'Quartier / zone',
                        hint: 'Saisir le quartier ou la zone',
                        onChanged: (value) {
                          _quartier = value;
                          _markManualAddress();
                        },
                      ),
                    ],
                    const SizedBox(height: 7),
                    _CompactEditableLocation(
                      controller: _notesController,
                      label: 'Repère / point de repère',
                      hint: 'Ex. Immeuble Orange Money, près du carrefour',
                      onChanged: (value) => _notes = value,
                    ),
                    const SizedBox(height: 7),
                    _CompactEditableLocation(
                      controller: _addressController,
                      label: 'Adresse complète',
                      hint: 'Rue, quartier, commune, ville',
                      onChanged: (value) {
                        _address = value;
                        _markManualAddress();
                      },
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 7),
              const _InfoStrip(
                text:
                    'Les frais de livraison seront calculés après confirmation de l’adresse.',
              ),
              const SizedBox(height: 7),
              _DeliveryQuickStats(preview: _preview, calculating: _calculating),
              const SizedBox(height: 7),
              _OrderSummaryCompact(
                subtotal: _preview?.subtotal ?? _selectedSubtotal,
                deliveryFee: _deliveryReady ? _preview!.deliveryFee : null,
                total: _deliveryReady ? _preview!.total : null,
                itemCount: _selectedItemsCount,
              ),
              if (_error != null) ...[
                const SizedBox(height: 8),
                _CheckoutError(message: _error!),
              ],
            ]),
          ),
        ),
      ],
    );
  }

  void _markManualAddress() {
    if (!mounted) return;
    final ready =
        _name.text.trim().isNotEmpty &&
        _phone.text.trim().isNotEmpty &&
        _commune.trim().isNotEmpty &&
        _quartier.trim().isNotEmpty &&
        _address.trim().isNotEmpty;
    if (ready && !_addressConfirmed) {
      setState(() {
        _addressConfirmed = true;
        _savedAddressId = null;
        _geoSource = 'mobile_manual';
      });
      unawaited(_refreshDeliveryPreview());
    }
  }

  Widget _buildReviewStage() {
    final preview = _preview;
    final deliveryReady = _deliveryReady;
    return CustomScrollView(
      slivers: [
        SliverToBoxAdapter(
          child: _CheckoutPageHeader(
            current: 2,
            completedThrough: 1,
            fillCurrentStep: true,
            onBack: () => setState(() => _stage = 1),
          ),
        ),
        SliverPadding(
          padding: const EdgeInsets.fromLTRB(14, 4, 14, 24),
          sliver: SliverList(
            delegate: SliverChildListDelegate([
              _CheckoutPanel(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        const Expanded(
                          child: Text(
                            'Adresse de livraison',
                            style: _CheckoutStyles.panelTitle,
                          ),
                        ),
                        TextButton(
                          onPressed: () => setState(() => _stage = 1),
                          child: const Text(
                            'Changer',
                            style: TextStyle(color: OvanieColors.orange),
                          ),
                        ),
                      ],
                    ),
                    Row(
                      children: [
                        Container(
                          width: 50,
                          height: 50,
                          decoration: BoxDecoration(
                            color: const Color(0xFFFFF2EE),
                            borderRadius: BorderRadius.circular(9),
                          ),
                          child: const Icon(
                            Icons.location_on_outlined,
                            color: OvanieColors.orange,
                            size: 27,
                          ),
                        ),
                        const SizedBox(width: 12),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                _addressTitle,
                                style: _CheckoutStyles.subTitle,
                              ),
                              const SizedBox(height: 3),
                              Text(
                                _address,
                                maxLines: 2,
                                overflow: TextOverflow.ellipsis,
                                style: const TextStyle(
                                  color: Color(0xFF53617B),
                                  fontSize: 11.3,
                                  height: 1.3,
                                ),
                              ),
                              const SizedBox(height: 3),
                              Text(
                                '${_name.text.trim()}  •  ${formatCiPhoneDisplay(_phone.text)}',
                                style: const TextStyle(
                                  color: OvanieColors.navy,
                                  fontSize: 11.2,
                                ),
                              ),
                            ],
                          ),
                        ),
                        const SizedBox(width: 8),
                        OutlinedButton.icon(
                          onPressed: _locating ? null : _useCurrentPosition,
                          style: OutlinedButton.styleFrom(
                            foregroundColor: OvanieColors.navy,
                            side: const BorderSide(color: Color(0xFFD7DEE9)),
                            shape: RoundedRectangleBorder(
                              borderRadius: BorderRadius.circular(8),
                            ),
                          ),
                          icon: const Icon(Icons.my_location_rounded, size: 18),
                          label: const Text(
                            'Ma position',
                            style: TextStyle(fontWeight: FontWeight.w800),
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 8),
              _CheckoutPanel(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text(
                      'Contact pour la livraison',
                      style: _CheckoutStyles.panelTitle,
                    ),
                    const SizedBox(height: 10),
                    _ContactRow(
                      icon: Icons.call_outlined,
                      iconColor: const Color(0xFF16A34A),
                      label: 'Téléphone',
                      value: formatCiPhoneDisplay(_phone.text),
                      onEdit: _editDeliveryPhone,
                    ),
                    const Divider(height: 18),
                    _ContactRow(
                      icon: Icons.chat_bubble_outline_rounded,
                      iconColor: OvanieColors.navy,
                      label: 'Consigne au livreur',
                      value: _notes.trim().isEmpty
                          ? 'Appeler avant d’arriver'
                          : _notes.trim(),
                      onEdit: _editDeliveryNote,
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 8),
              _CheckoutPanel(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text('Livraison', style: _CheckoutStyles.panelTitle),
                    const SizedBox(height: 10),
                    _DeliveryMetrics(preview: preview),
                    const SizedBox(height: 9),
                    Container(
                      width: double.infinity,
                      padding: const EdgeInsets.symmetric(
                        horizontal: 12,
                        vertical: 9,
                      ),
                      decoration: BoxDecoration(
                        color: deliveryReady
                            ? const Color(0xFFF1FAF2)
                            : const Color(0xFFFFF7EA),
                        borderRadius: BorderRadius.circular(8),
                      ),
                      child: Row(
                        children: [
                          Icon(
                            deliveryReady
                                ? Icons.local_shipping_outlined
                                : Icons.schedule_outlined,
                            color: deliveryReady
                                ? const Color(0xFF16A34A)
                                : const Color(0xFFE08A00),
                            size: 24,
                          ),
                          const SizedBox(width: 10),
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  deliveryReady
                                      ? 'Livraison disponible'
                                      : 'Calcul de livraison en cours',
                                  style: TextStyle(
                                    color: deliveryReady
                                        ? const Color(0xFF14903A)
                                        : const Color(0xFFC37500),
                                    fontWeight: FontWeight.w800,
                                    fontSize: 11.8,
                                  ),
                                ),
                                const SizedBox(height: 2),
                                Text(
                                  deliveryReady
                                      ? 'Calculée selon votre adresse chantier'
                                      : (preview?.deliveryMessage
                                                    .trim()
                                                    .isNotEmpty ==
                                                true
                                            ? preview!.deliveryMessage
                                            : 'Nous vérifions votre zone de livraison.'),
                                  style: const TextStyle(
                                    color: Color(0xFF526181),
                                    fontSize: 10.3,
                                  ),
                                ),
                              ],
                            ),
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: 8),
                    const _InfoStrip(
                      text:
                          'Les frais de livraison sont calculés pour l’adresse renseignée.',
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 8),
              _CheckoutPanel(
                child: _ReviewSummary(
                  preview: preview,
                  itemCount: _selectedItemsCount,
                ),
              ),
              const SizedBox(height: 8),
              const _TrustStrip(),
              if (_error != null) ...[
                const SizedBox(height: 8),
                _CheckoutError(message: _error!),
              ],
            ]),
          ),
        ),
      ],
    );
  }

  Widget _buildPaymentStage() {
    final preview = _preview;
    final codEnabled = _cashOnDeliveryEnabled;
    final onlineEnabled = _onlinePaymentEnabled;
    final onlineSelected = _paymentMethod == 'paydunya';
    final codSelected = _paymentMethod == 'cash_on_delivery';
    final operators = _availableOnlineOperators;

    return CustomScrollView(
      slivers: [
        SliverToBoxAdapter(
          child: _CheckoutPageHeader(
            current: 2,
            completedThrough: 1,
            showSecureLabel: true,
            fillCurrentStep: true,
            onBack: () => setState(() {
              _stage = 1;
              _error = null;
            }),
          ),
        ),
        SliverPadding(
          padding: const EdgeInsets.fromLTRB(18, 14, 18, 24),
          sliver: SliverList(
            delegate: SliverChildListDelegate([
              const Text(
                'Choisissez votre mode de paiement',
                style: TextStyle(
                  color: OvanieColors.navy,
                  fontSize: 18.5,
                  fontWeight: FontWeight.w900,
                ),
              ),
              const SizedBox(height: 4),
              const Text(
                'Sélectionnez le mode de paiement qui vous convient.',
                style: TextStyle(color: Color(0xFF59657B), fontSize: 11.6),
              ),
              const SizedBox(height: 13),
              IntrinsicHeight(
                child: Row(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Expanded(
                      child: _PaymentChoiceCard(
                        title: 'Paiement à la livraison',
                        subtitle:
                            'Payez en espèces à la réception de votre commande.',
                        bullets: const <String>[
                          'Aucun paiement en ligne requis',
                          'Vérifiez vos produits avant de payer',
                          'Disponible dans toutes nos zones de livraison',
                        ],
                        icon: Icons.payments_outlined,
                        selected: codSelected,
                        enabled: codEnabled,
                        online: false,
                        onTap: _selectCashOnDelivery,
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: _PaymentChoiceCard(
                        title: 'Paiement en ligne',
                        subtitle: 'Payez maintenant de manière sécurisée.',
                        bullets: const <String>[
                          'Mobile Money & carte bancaire',
                          'Confirmation immédiate',
                          'Transactions sécurisées',
                        ],
                        icon: Icons.credit_card_rounded,
                        selected: onlineSelected,
                        enabled: onlineEnabled,
                        online: true,
                        onTap: _selectOnlinePayment,
                      ),
                    ),
                  ],
                ),
              ),
              if (onlineSelected) ...[
                const SizedBox(height: 12),
                Container(
                  padding: const EdgeInsets.fromLTRB(12, 12, 12, 5),
                  decoration: BoxDecoration(
                    color: Colors.white,
                    borderRadius: BorderRadius.circular(11),
                    border: Border.all(color: const Color(0xFFDDE3EC)),
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Row(
                        children: [
                          Expanded(
                            child: Text(
                              'Moyens de paiement en ligne',
                              style: TextStyle(
                                color: OvanieColors.navy,
                                fontSize: 14.5,
                                fontWeight: FontWeight.w900,
                              ),
                            ),
                          ),
                          Icon(
                            Icons.keyboard_arrow_up_rounded,
                            color: OvanieColors.orange,
                            size: 24,
                          ),
                        ],
                      ),
                      const SizedBox(height: 8),
                      for (final operator in operators)
                        Padding(
                          padding: const EdgeInsets.only(bottom: 7),
                          child: _OnlineOperatorTile(
                            operator: operator,
                            selected: _onlineOperator == operator.code,
                            enabled: onlineEnabled,
                            onTap: () => _selectOnlineOperator(operator),
                          ),
                        ),
                      if (_onlineOperator.isNotEmpty &&
                          _onlineOperator != 'card') ...[
                        const SizedBox(height: 5),
                        const Text(
                          'Numéro Mobile Money',
                          style: TextStyle(
                            color: OvanieColors.navy,
                            fontSize: 12.5,
                            fontWeight: FontWeight.w900,
                          ),
                        ),
                        const SizedBox(height: 7),
                        TextFormField(
                          key: ValueKey<String>(
                            'payment-phone-$_onlineOperator',
                          ),
                          initialValue: formatCiPhoneDisplay(
                            _paymentPhone,
                            includeCountryCode: false,
                          ),
                          keyboardType: TextInputType.phone,
                          textInputAction: TextInputAction.done,
                          autofillHints: const [AutofillHints.telephoneNumber],
                          inputFormatters: const [CiPhoneInputFormatter()],
                          decoration: InputDecoration(
                            hintText: '07 00 00 00 00',
                            prefixText: '+225  ',
                            helperText:
                                'Saisissez le numéro qui sera débité pour ce paiement.',
                            prefixIcon: const Icon(Icons.phone_android_rounded),
                            border: OutlineInputBorder(
                              borderRadius: BorderRadius.circular(10),
                            ),
                          ),
                          onChanged: (value) {
                            setState(() {
                              _paymentPhone = normalizeCiPhoneForApi(value);
                              _error = null;
                            });
                          },
                        ),
                        const SizedBox(height: 8),
                      ],
                      if (_onlineOperator == 'orange') ...[
                        const Text(
                          'OTP Orange Money',
                          style: TextStyle(
                            color: OvanieColors.navy,
                            fontSize: 12.5,
                            fontWeight: FontWeight.w900,
                          ),
                        ),
                        const SizedBox(height: 7),
                        TextFormField(
                          key: const ValueKey<String>('orange-money-otp'),
                          initialValue: _orangeOtp,
                          keyboardType: TextInputType.number,
                          textInputAction: TextInputAction.done,
                          decoration: InputDecoration(
                            hintText: 'Saisissez l’OTP si Orange Money vous le demande',
                            helperText:
                                'Ce code n’est demandé que lorsque l’opérateur Orange l’exige.',
                            prefixIcon: const Icon(Icons.password_rounded),
                            border: OutlineInputBorder(
                              borderRadius: BorderRadius.circular(10),
                            ),
                          ),
                          onChanged: (value) {
                            setState(() {
                              _orangeOtp = value.trim();
                              _error = null;
                            });
                          },
                        ),
                        const SizedBox(height: 8),
                      ],
                    ],
                  ),
                ),
              ],
              if (!codEnabled &&
                  (_cashOnDeliveryOption?.reason.trim().isNotEmpty ??
                      false)) ...[
                const SizedBox(height: 8),
                _CheckoutError(message: _cashOnDeliveryOption!.reason),
              ],
              if (!onlineEnabled &&
                  (_onlinePaymentOption?.reason.trim().isNotEmpty ??
                      false)) ...[
                const SizedBox(height: 8),
                _CheckoutError(message: _onlinePaymentOption!.reason),
              ],
              const SizedBox(height: 12),
              _CheckoutPanel(
                child: _PaymentOrderSummary(
                  preview: preview,
                  itemCount: _selectedItemsCount,
                ),
              ),
              const SizedBox(height: 10),
              const _TrustStrip(),
              if (_error != null) ...[
                const SizedBox(height: 9),
                _CheckoutError(message: _error!),
              ],
            ]),
          ),
        ),
      ],
    );
  }
}

class _CheckoutPageHeader extends StatelessWidget {
  final int current;
  final int completedThrough;
  final VoidCallback onBack;
  final bool showSecureLabel;
  final bool fillCurrentStep;

  const _CheckoutPageHeader({
    required this.current,
    required this.onBack,
    this.completedThrough = 0,
    this.showSecureLabel = false,
    this.fillCurrentStep = false,
  });

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        SizedBox(
          height: 58,
          child: Stack(
            alignment: Alignment.center,
            children: [
              Align(
                alignment: Alignment.centerLeft,
                child: IconButton(
                  onPressed: onBack,
                  icon: const Icon(
                    Icons.arrow_back_rounded,
                    color: OvanieColors.navy,
                    size: 27,
                  ),
                ),
              ),
              const Text(
                'Checkout',
                textAlign: TextAlign.center,
                style: TextStyle(
                  color: OvanieColors.navy,
                  fontSize: 23,
                  fontWeight: FontWeight.w900,
                ),
              ),
              Align(
                alignment: Alignment.centerRight,
                child: Padding(
                  padding: const EdgeInsets.only(right: 10),
                  child: showSecureLabel
                      ? const Row(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            Icon(
                              Icons.shield_outlined,
                              color: OvanieColors.navy,
                              size: 20,
                            ),
                            SizedBox(width: 5),
                            Text(
                              'Paiement 100% sécurisé',
                              style: TextStyle(
                                color: OvanieColors.navy,
                                fontSize: 9.5,
                                fontWeight: FontWeight.w700,
                              ),
                            ),
                          ],
                        )
                      : const Icon(
                          Icons.shield_outlined,
                          color: OvanieColors.navy,
                          size: 26,
                        ),
                ),
              ),
            ],
          ),
        ),
        Padding(
          padding: const EdgeInsets.fromLTRB(22, 2, 22, 8),
          child: _CheckoutProgressBar(
            current: current,
            completedThrough: completedThrough,
            fillCurrentStep: fillCurrentStep,
          ),
        ),
      ],
    );
  }
}

class _CheckoutProgressBar extends StatelessWidget {
  final int current;
  final int completedThrough;
  final bool fillCurrentStep;

  const _CheckoutProgressBar({
    required this.current,
    required this.completedThrough,
    this.fillCurrentStep = false,
  });

  @override
  Widget build(BuildContext context) {
    const labels = ['Livraison', 'Paiement', 'Confirmation'];
    return LayoutBuilder(
      builder: (context, constraints) {
        final gap = constraints.maxWidth / labels.length;
        return SizedBox(
          height: 70,
          child: Stack(
            children: [
              Positioned(
                left: gap / 2,
                right: gap / 2,
                top: 18,
                child: Container(height: 2, color: const Color(0xFFD7DCE5)),
              ),
              if (current > 1)
                Positioned(
                  left: gap / 2,
                  top: 18,
                  width: gap * (current - 1),
                  child: Container(height: 2, color: OvanieColors.orange),
                ),
              Row(
                children: List.generate(labels.length, (index) {
                  final step = index + 1;
                  final done = step <= completedThrough;
                  final active = step == current;
                  return Expanded(
                    child: Column(
                      children: [
                        Container(
                          width: 34,
                          height: 34,
                          alignment: Alignment.center,
                          decoration: BoxDecoration(
                            shape: BoxShape.circle,
                            color:
                                done ||
                                    (current == 1 && step == 1) ||
                                    (fillCurrentStep && active)
                                ? const Color(0xFFFF3E0D)
                                : Colors.white,
                            border: Border.all(
                              color:
                                  (done ||
                                      active ||
                                      (current == 1 && step == 1))
                                  ? const Color(0xFFFF3E0D)
                                  : const Color(0xFFC7CFDD),
                              width: 1.3,
                            ),
                          ),
                          child: done
                              ? const Icon(
                                  Icons.check_rounded,
                                  color: Colors.white,
                                  size: 21,
                                )
                              : Text(
                                  '$step',
                                  style: TextStyle(
                                    color:
                                        (current == 1 && step == 1) ||
                                            (fillCurrentStep && active)
                                        ? Colors.white
                                        : active
                                        ? OvanieColors.orange
                                        : const Color(0xFF70798C),
                                    fontWeight: FontWeight.w800,
                                  ),
                                ),
                        ),
                        const SizedBox(height: 5),
                        Text(
                          labels[index],
                          style: TextStyle(
                            color:
                                (done || active || (current == 1 && step == 1))
                                ? OvanieColors.orange
                                : const Color(0xFF59657B),
                            fontSize: 10.5,
                          ),
                        ),
                      ],
                    ),
                  );
                }),
              ),
            ],
          ),
        );
      },
    );
  }
}

class _CheckoutPanel extends StatelessWidget {
  final Widget child;
  final EdgeInsetsGeometry padding;

  const _CheckoutPanel({
    super.key,
    required this.child,
    this.padding = const EdgeInsets.all(12),
  });

  @override
  Widget build(BuildContext context) => Container(
    width: double.infinity,
    padding: padding,
    decoration: BoxDecoration(
      color: Colors.white,
      borderRadius: BorderRadius.circular(11),
      border: Border.all(color: const Color(0xFFE0E5EC)),
      boxShadow: const [
        BoxShadow(
          color: Color(0x08071B48),
          blurRadius: 10,
          offset: Offset(0, 3),
        ),
      ],
    ),
    child: child,
  );
}

class _CheckoutStyles {
  static const panelTitle = TextStyle(
    color: OvanieColors.navy,
    fontSize: 17,
    fontWeight: FontWeight.w900,
  );
  static const subTitle = TextStyle(
    color: OvanieColors.navy,
    fontSize: 12.5,
    fontWeight: FontWeight.w900,
  );
}

class _SearchLikeField extends StatelessWidget {
  final String label;
  final VoidCallback onTap;

  const _SearchLikeField({required this.label, required this.onTap});

  @override
  Widget build(BuildContext context) => InkWell(
    onTap: onTap,
    borderRadius: BorderRadius.circular(8),
    child: Container(
      height: 45,
      padding: const EdgeInsets.symmetric(horizontal: 12),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(8),
        border: Border.all(color: const Color(0xFFD9E0EA)),
      ),
      child: Row(
        children: [
          const Icon(Icons.search_rounded, color: OvanieColors.navy, size: 20),
          const SizedBox(width: 9),
          Expanded(
            child: Text(
              label,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: const TextStyle(color: Color(0xFF5C6880), fontSize: 10.5),
            ),
          ),
        ],
      ),
    ),
  );
}

class _CheckoutMapPreview extends StatelessWidget {
  final double? latitude;
  final double? longitude;

  const _CheckoutMapPreview({required this.latitude, required this.longitude});

  bool get _hasPosition =>
      latitude != null &&
      longitude != null &&
      latitude!.isFinite &&
      longitude!.isFinite &&
      !(latitude == 0 && longitude == 0);

  @override
  Widget build(BuildContext context) {
    // Avant que l'utilisateur n'autorise le GPS, la carte peut afficher la zone
    // générale d'Abidjan, mais aucun marqueur n'est affiché : on ne présente
    // donc jamais ce centre par défaut comme la position réelle du client.
    final center = _hasPosition
        ? LatLng(latitude!, longitude!)
        : const LatLng(5.359952, -4.008256);
    final zoom = _hasPosition ? 17.4 : 11.8;

    return ClipRRect(
      borderRadius: BorderRadius.circular(8),
      child: SizedBox(
        height: 118,
        child: FlutterMap(
          key: ValueKey<String>(
            _hasPosition
                ? 'checkout-map-${latitude!.toStringAsFixed(6)}-${longitude!.toStringAsFixed(6)}'
                : 'checkout-map-empty',
          ),
          options: MapOptions(
            initialCenter: center,
            initialZoom: zoom,
            minZoom: 4,
            maxZoom: 19,
            interactionOptions: const InteractionOptions(
              flags: InteractiveFlag.all & ~InteractiveFlag.rotate,
            ),
          ),
          children: [
            TileLayer(
              urlTemplate: 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
              userAgentPackageName: 'com.ovanie.ovanie_app',
              maxNativeZoom: 19,
            ),
            if (_hasPosition)
              MarkerLayer(
                markers: [
                  Marker(
                    point: center,
                    width: 48,
                    height: 48,
                    alignment: Alignment.topCenter,
                    child: const Icon(
                      Icons.location_on_rounded,
                      color: OvanieColors.orange,
                      size: 44,
                      shadows: [
                        Shadow(color: Color(0x42000000), blurRadius: 5),
                      ],
                    ),
                  ),
                ],
              ),
          ],
        ),
      ),
    );
  }
}

class _SavedAddressTile extends StatelessWidget {
  final CheckoutSavedAddress item;
  final bool selected;
  final VoidCallback onTap;

  const _SavedAddressTile({
    required this.item,
    required this.selected,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) => InkWell(
    onTap: onTap,
    borderRadius: BorderRadius.circular(9),
    child: Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(9),
        border: Border.all(
          color: selected ? OvanieColors.orange : const Color(0xFFDDE3EC),
          width: selected ? 1.2 : 1,
        ),
      ),
      child: Row(
        children: [
          Icon(
            selected ? Icons.radio_button_checked : Icons.radio_button_off,
            color: selected ? OvanieColors.orange : const Color(0xFF7D8799),
            size: 22,
          ),
          const SizedBox(width: 9),
          Container(
            width: 44,
            height: 44,
            decoration: BoxDecoration(
              color: selected
                  ? const Color(0xFFFFF1EC)
                  : const Color(0xFFF5F7F9),
              borderRadius: BorderRadius.circular(9),
            ),
            child: Icon(
              item.label.toLowerCase().contains('bureau')
                  ? Icons.business_center_outlined
                  : Icons.location_on_outlined,
              color: selected ? OvanieColors.orange : const Color(0xFF6E7890),
            ),
          ),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Expanded(
                      child: Text(
                        item.label.trim().isEmpty ? 'Adresse' : item.label,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(
                          color: OvanieColors.navy,
                          fontSize: 11.5,
                          fontWeight: FontWeight.w900,
                        ),
                      ),
                    ),
                    if (item.isDefault)
                      Container(
                        padding: const EdgeInsets.symmetric(
                          horizontal: 7,
                          vertical: 4,
                        ),
                        decoration: BoxDecoration(
                          color: const Color(0xFFFFF1EB),
                          borderRadius: BorderRadius.circular(12),
                        ),
                        child: const Text(
                          'Par défaut',
                          style: TextStyle(
                            color: OvanieColors.orange,
                            fontSize: 8.8,
                          ),
                        ),
                      ),
                  ],
                ),
                const SizedBox(height: 2),
                Text(
                  [
                    item.city,
                    item.commune,
                    item.quartier,
                  ].where((e) => e.trim().isNotEmpty).join(', '),
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    color: Color(0xFF526181),
                    fontSize: 9.5,
                  ),
                ),
                if (item.address.trim().isNotEmpty)
                  Text(
                    item.address,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      color: Color(0xFF526181),
                      fontSize: 9.5,
                    ),
                  ),
                if (item.recipientName.trim().isNotEmpty ||
                    item.phone.trim().isNotEmpty)
                  Text(
                    '${item.recipientName}${item.recipientName.trim().isNotEmpty && item.phone.trim().isNotEmpty ? '  •  ' : ''}${formatCiPhoneDisplay(item.phone)}',
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      color: OvanieColors.navy,
                      fontSize: 9.4,
                    ),
                  ),
              ],
            ),
          ),
          const SizedBox(width: 4),
          const Icon(
            Icons.more_vert_rounded,
            color: OvanieColors.navy,
            size: 19,
          ),
        ],
      ),
    ),
  );
}

class _EmptySavedAddresses extends StatelessWidget {
  final VoidCallback onTap;
  const _EmptySavedAddresses({required this.onTap});

  @override
  Widget build(BuildContext context) => InkWell(
    onTap: onTap,
    child: Container(
      margin: const EdgeInsets.only(bottom: 7),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: const Color(0xFFF8FAFD),
        borderRadius: BorderRadius.circular(9),
        border: Border.all(color: OvanieColors.border),
      ),
      child: const Row(
        children: [
          Icon(Icons.location_off_outlined, color: OvanieColors.muted),
          SizedBox(width: 10),
          Expanded(
            child: Text(
              'Aucune adresse enregistrée. Ajoutez votre adresse de livraison.',
              style: TextStyle(color: OvanieColors.muted, fontSize: 10.5),
            ),
          ),
        ],
      ),
    ),
  );
}

class _CompactTextField extends StatelessWidget {
  final TextEditingController controller;
  final String label;
  final TextInputType? keyboardType;
  final IconData? prefixIcon;
  final List<TextInputFormatter>? inputFormatters;
  final ValueChanged<String>? onChanged;

  const _CompactTextField({
    required this.controller,
    required this.label,
    this.keyboardType,
    this.prefixIcon,
    this.inputFormatters,
    this.onChanged,
  });

  @override
  Widget build(BuildContext context) => SizedBox(
    height: 44,
    child: TextField(
      controller: controller,
      keyboardType: keyboardType,
      inputFormatters: inputFormatters,
      onChanged: onChanged,
      style: const TextStyle(color: OvanieColors.navy, fontSize: 10.8),
      decoration: InputDecoration(
        labelText: label,
        labelStyle: const TextStyle(fontSize: 10.4),
        prefixIcon: prefixIcon == null ? null : Icon(prefixIcon, size: 18),
        contentPadding: const EdgeInsets.symmetric(
          horizontal: 11,
          vertical: 10,
        ),
        border: OutlineInputBorder(borderRadius: BorderRadius.circular(8)),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(8),
          borderSide: const BorderSide(color: Color(0xFFD9E0EA)),
        ),
      ),
    ),
  );
}

class _CompactEditableLocation extends StatelessWidget {
  final TextEditingController controller;
  final String label;
  final String hint;
  final ValueChanged<String> onChanged;

  const _CompactEditableLocation({
    required this.controller,
    required this.label,
    required this.hint,
    required this.onChanged,
  });

  @override
  Widget build(BuildContext context) => SizedBox(
    height: 44,
    child: TextField(
      controller: controller,
      onChanged: onChanged,
      textInputAction: TextInputAction.next,
      style: const TextStyle(color: OvanieColors.navy, fontSize: 10.8),
      decoration: InputDecoration(
        labelText: label,
        hintText: hint,
        labelStyle: const TextStyle(fontSize: 10.4),
        hintStyle: const TextStyle(fontSize: 10.3, color: Color(0xFF9AA5B5)),
        contentPadding: const EdgeInsets.symmetric(
          horizontal: 11,
          vertical: 10,
        ),
        border: OutlineInputBorder(borderRadius: BorderRadius.circular(8)),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(8),
          borderSide: const BorderSide(color: Color(0xFFD9E0EA)),
        ),
      ),
    ),
  );
}

class _DeliveryEditSheet extends StatefulWidget {
  final String title;
  final String label;
  final String initialValue;
  final String hint;
  final bool phone;
  final bool allowEmpty;

  const _DeliveryEditSheet({
    required this.title,
    required this.label,
    required this.initialValue,
    required this.hint,
    required this.phone,
    this.allowEmpty = false,
  });

  @override
  State<_DeliveryEditSheet> createState() => _DeliveryEditSheetState();
}

class _DeliveryEditSheetState extends State<_DeliveryEditSheet> {
  final _formKey = GlobalKey<FormState>();
  late final TextEditingController _controller;

  @override
  void initState() {
    super.initState();
    _controller = TextEditingController(text: widget.initialValue);
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  void _submit() {
    if (!(_formKey.currentState?.validate() ?? false)) return;
    Navigator.of(context).pop(_controller.text.trim());
  }

  @override
  Widget build(BuildContext context) {
    final bottom = MediaQuery.viewInsetsOf(context).bottom;
    return Container(
      padding: EdgeInsets.fromLTRB(20, 12, 20, 20 + bottom),
      decoration: const BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
      ),
      child: Form(
        key: _formKey,
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Center(
              child: Container(
                width: 42,
                height: 4,
                decoration: BoxDecoration(
                  color: const Color(0xFFD9E0EA),
                  borderRadius: BorderRadius.circular(99),
                ),
              ),
            ),
            const SizedBox(height: 18),
            Text(
              widget.title,
              style: const TextStyle(
                color: OvanieColors.navy,
                fontSize: 19,
                fontWeight: FontWeight.w900,
              ),
            ),
            const SizedBox(height: 16),
            TextFormField(
              controller: _controller,
              autofocus: true,
              keyboardType: widget.phone
                  ? TextInputType.phone
                  : TextInputType.text,
              textInputAction: TextInputAction.done,
              onFieldSubmitted: (_) => _submit(),
              inputFormatters: widget.phone
                  ? <TextInputFormatter>[CiPhoneInputFormatter()]
                  : null,
              minLines: widget.phone ? 1 : 2,
              maxLines: widget.phone ? 1 : 4,
              decoration: InputDecoration(
                labelText: widget.label,
                hintText: widget.hint,
                prefixIcon: widget.phone
                    ? const Icon(Icons.call_outlined, color: OvanieColors.navy)
                    : const Icon(
                        Icons.chat_bubble_outline_rounded,
                        color: OvanieColors.navy,
                      ),
                border: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(13),
                ),
                enabledBorder: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(13),
                  borderSide: const BorderSide(color: Color(0xFFD9E0EA)),
                ),
                focusedBorder: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(13),
                  borderSide: const BorderSide(
                    color: OvanieColors.orange,
                    width: 1.4,
                  ),
                ),
              ),
              validator: (value) {
                final clean = (value ?? '').trim();
                if (clean.isEmpty && !widget.allowEmpty) {
                  return 'Ce champ est obligatoire.';
                }
                if (widget.phone) {
                  final digits = clean.replaceAll(RegExp(r'\D+'), '');
                  if (digits.length < 10) {
                    return 'Renseignez un numéro de téléphone valide.';
                  }
                }
                return null;
              },
            ),
            const SizedBox(height: 18),
            Row(
              children: [
                Expanded(
                  child: OutlinedButton(
                    onPressed: () => Navigator.of(context).pop(),
                    style: OutlinedButton.styleFrom(
                      foregroundColor: OvanieColors.navy,
                      minimumSize: const Size.fromHeight(48),
                      side: const BorderSide(color: Color(0xFFD9E0EA)),
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(12),
                      ),
                    ),
                    child: const Text(
                      'Annuler',
                      style: TextStyle(fontWeight: FontWeight.w800),
                    ),
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  flex: 2,
                  child: FilledButton(
                    onPressed: _submit,
                    style: FilledButton.styleFrom(
                      backgroundColor: OvanieColors.orange,
                      foregroundColor: Colors.white,
                      minimumSize: const Size.fromHeight(48),
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(12),
                      ),
                    ),
                    child: const Text(
                      'Enregistrer',
                      style: TextStyle(fontWeight: FontWeight.w900),
                    ),
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

class _CompactLocationDropdown extends StatelessWidget {
  final String label;
  final String hint;
  final String? value;
  final List<String> items;
  final ValueChanged<String?>? onChanged;

  const _CompactLocationDropdown({
    required this.label,
    required this.hint,
    required this.value,
    required this.items,
    required this.onChanged,
  });

  @override
  Widget build(BuildContext context) {
    final uniqueItems = <String>[];
    final seen = <String>{};
    for (final item in items) {
      final clean = item.trim();
      final key = clean.toLowerCase();
      if (clean.isNotEmpty && seen.add(key)) uniqueItems.add(clean);
    }

    final effectiveValue =
        value != null &&
            uniqueItems.any(
              (item) => item.toLowerCase() == value!.trim().toLowerCase(),
            )
        ? value!.trim()
        : null;
    final enabled = onChanged != null;

    return SizedBox(
      height: 44,
      child: Material(
        color: Colors.transparent,
        child: InkWell(
          onTap: !enabled
              ? null
              : () async {
                  FocusManager.instance.primaryFocus?.unfocus();
                  final selected = await showModalBottomSheet<String>(
                    context: context,
                    isScrollControlled: true,
                    useSafeArea: true,
                    backgroundColor: Colors.transparent,
                    builder: (_) => _LocationPickerSheet(
                      title: label,
                      items: uniqueItems,
                      selected: effectiveValue,
                    ),
                  );
                  if (selected != null) onChanged?.call(selected);
                },
          borderRadius: BorderRadius.circular(8),
          child: InputDecorator(
            isEmpty: effectiveValue == null,
            decoration: InputDecoration(
              labelText: label,
              enabled: enabled,
              labelStyle: const TextStyle(fontSize: 10.4),
              contentPadding: const EdgeInsets.symmetric(
                horizontal: 11,
                vertical: 9,
              ),
              border: OutlineInputBorder(
                borderRadius: BorderRadius.circular(8),
              ),
              enabledBorder: OutlineInputBorder(
                borderRadius: BorderRadius.circular(8),
                borderSide: const BorderSide(color: Color(0xFFD9E0EA)),
              ),
              disabledBorder: OutlineInputBorder(
                borderRadius: BorderRadius.circular(8),
                borderSide: const BorderSide(color: Color(0xFFE7EAF0)),
              ),
            ),
            child: Row(
              children: [
                Expanded(
                  child: Text(
                    effectiveValue ?? hint,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: TextStyle(
                      color: effectiveValue == null
                          ? const Color(0xFF9AA5B5)
                          : OvanieColors.navy,
                      fontSize: 10.8,
                      fontWeight: effectiveValue == null
                          ? FontWeight.w500
                          : FontWeight.w700,
                    ),
                  ),
                ),
                Icon(
                  Icons.keyboard_arrow_down_rounded,
                  color: enabled ? OvanieColors.navy : const Color(0xFFB8C0CC),
                  size: 19,
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _LocationPickerSheet extends StatefulWidget {
  final String title;
  final List<String> items;
  final String? selected;

  const _LocationPickerSheet({
    required this.title,
    required this.items,
    required this.selected,
  });

  @override
  State<_LocationPickerSheet> createState() => _LocationPickerSheetState();
}

class _LocationPickerSheetState extends State<_LocationPickerSheet> {
  final _search = TextEditingController();
  String _query = '';

  @override
  void dispose() {
    _search.dispose();
    super.dispose();
  }

  List<String> get _filtered {
    final q = _query.trim().toLowerCase();
    if (q.isEmpty) return widget.items;
    return widget.items
        .where((item) => item.toLowerCase().contains(q))
        .toList(growable: false);
  }

  @override
  Widget build(BuildContext context) {
    final filtered = _filtered;
    return FractionallySizedBox(
      heightFactor: 0.72,
      child: Container(
        decoration: const BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
        ),
        child: Column(
          children: [
            const SizedBox(height: 10),
            Container(
              width: 42,
              height: 4,
              decoration: BoxDecoration(
                color: const Color(0xFFD9E0EA),
                borderRadius: BorderRadius.circular(99),
              ),
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(20, 16, 20, 12),
              child: Row(
                children: [
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          'Choisir ${widget.title.toLowerCase()}',
                          style: const TextStyle(
                            color: OvanieColors.navy,
                            fontSize: 18,
                            fontWeight: FontWeight.w900,
                          ),
                        ),
                        const SizedBox(height: 2),
                        Text(
                          '${widget.items.length} option${widget.items.length > 1 ? 's' : ''}',
                          style: const TextStyle(
                            color: Color(0xFF7A8599),
                            fontSize: 11.5,
                          ),
                        ),
                      ],
                    ),
                  ),
                  IconButton(
                    onPressed: () => Navigator.of(context).pop(),
                    icon: const Icon(
                      Icons.close_rounded,
                      color: OvanieColors.navy,
                    ),
                  ),
                ],
              ),
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(20, 0, 20, 12),
              child: TextField(
                controller: _search,
                autofocus: false,
                onChanged: (value) => setState(() => _query = value),
                decoration: InputDecoration(
                  hintText: 'Rechercher…',
                  prefixIcon: const Icon(Icons.search_rounded, size: 20),
                  suffixIcon: _query.isEmpty
                      ? null
                      : IconButton(
                          onPressed: () {
                            _search.clear();
                            setState(() => _query = '');
                          },
                          icon: const Icon(Icons.close_rounded, size: 18),
                        ),
                  filled: true,
                  fillColor: const Color(0xFFF7F9FC),
                  contentPadding: const EdgeInsets.symmetric(vertical: 12),
                  border: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(12),
                    borderSide: BorderSide.none,
                  ),
                ),
              ),
            ),
            const Divider(height: 1),
            Expanded(
              child: filtered.isEmpty
                  ? const Center(
                      child: Text(
                        'Aucun résultat',
                        style: TextStyle(
                          color: Color(0xFF7A8599),
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                    )
                  : ListView.separated(
                      padding: const EdgeInsets.symmetric(vertical: 6),
                      itemCount: filtered.length,
                      separatorBuilder: (_, __) =>
                          const Divider(height: 1, indent: 20, endIndent: 20),
                      itemBuilder: (context, index) {
                        final item = filtered[index];
                        final selected =
                            widget.selected?.trim().toLowerCase() ==
                            item.toLowerCase();
                        return ListTile(
                          contentPadding: const EdgeInsets.symmetric(
                            horizontal: 20,
                            vertical: 2,
                          ),
                          title: Text(
                            item,
                            style: TextStyle(
                              color: OvanieColors.navy,
                              fontSize: 14,
                              fontWeight: selected
                                  ? FontWeight.w900
                                  : FontWeight.w700,
                            ),
                          ),
                          trailing: selected
                              ? const Icon(
                                  Icons.check_circle_rounded,
                                  color: OvanieColors.orange,
                                )
                              : const Icon(
                                  Icons.chevron_right_rounded,
                                  color: Color(0xFFA2ABBA),
                                ),
                          onTap: () => Navigator.of(context).pop(item),
                        );
                      },
                    ),
            ),
          ],
        ),
      ),
    );
  }
}

class _InfoStrip extends StatelessWidget {
  final String text;
  const _InfoStrip({required this.text});

  @override
  Widget build(BuildContext context) => Container(
    width: double.infinity,
    padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 9),
    decoration: BoxDecoration(
      color: const Color(0xFFF3F8FF),
      borderRadius: BorderRadius.circular(8),
      border: Border.all(color: const Color(0xFFD8E6FA)),
    ),
    child: Row(
      children: [
        const Icon(
          Icons.info_outline_rounded,
          color: Color(0xFF1266F1),
          size: 19,
        ),
        const SizedBox(width: 9),
        Expanded(
          child: Text(
            text,
            style: const TextStyle(color: OvanieColors.navy, fontSize: 10.5),
          ),
        ),
      ],
    ),
  );
}

class _DeliveryQuickStats extends StatelessWidget {
  final CheckoutPreview? preview;
  final bool calculating;
  const _DeliveryQuickStats({required this.preview, required this.calculating});

  @override
  Widget build(BuildContext context) {
    final vehicle = preview?.recommendedVehicle.trim().isNotEmpty == true
        ? preview!.recommendedVehicle
        : 'À confirmer';
    final zone = preview?.deliveryZone.trim().isNotEmpty == true
        ? preview!.deliveryZone
        : 'Abidjan';
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 10),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(9),
        border: Border.all(color: const Color(0xFFE0E5EC)),
      ),
      child: Row(
        children: [
          _MiniMetric(
            icon: Icons.location_on_outlined,
            label: 'Zone',
            value: zone,
          ),
          const _MiniDivider(),
          _MiniMetric(
            icon: Icons.electric_rickshaw_outlined,
            label: 'Véhicule estimé',
            value: vehicle,
          ),
          const _MiniDivider(),
          _MiniMetric(
            icon: Icons.schedule_outlined,
            label: 'Livraison estimée',
            value: calculating ? 'Calcul…' : '24 à 48 h',
          ),
        ],
      ),
    );
  }
}

class _MiniMetric extends StatelessWidget {
  final IconData icon;
  final String label;
  final String value;
  const _MiniMetric({
    required this.icon,
    required this.label,
    required this.value,
  });

  @override
  Widget build(BuildContext context) => Expanded(
    child: Row(
      children: [
        Icon(icon, color: OvanieColors.navy, size: 22),
        const SizedBox(width: 7),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                label,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(color: Color(0xFF59657B), fontSize: 8.7),
              ),
              const SizedBox(height: 2),
              Text(
                value,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(
                  color: OvanieColors.navy,
                  fontSize: 10.4,
                  fontWeight: FontWeight.w800,
                ),
              ),
            ],
          ),
        ),
      ],
    ),
  );
}

class _MiniDivider extends StatelessWidget {
  const _MiniDivider();
  @override
  Widget build(BuildContext context) => Container(
    width: 1,
    height: 30,
    margin: const EdgeInsets.symmetric(horizontal: 8),
    color: OvanieColors.border,
  );
}

class _OrderSummaryCompact extends StatelessWidget {
  final double subtotal;
  final double? deliveryFee;
  final double? total;
  final int itemCount;

  const _OrderSummaryCompact({
    required this.subtotal,
    required this.deliveryFee,
    required this.total,
    required this.itemCount,
  });

  @override
  Widget build(BuildContext context) {
    final finalTotal = total ?? subtotal;
    return _CheckoutPanel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              const Expanded(
                child: Text(
                  'Récapitulatif de la commande',
                  style: _CheckoutStyles.subTitle,
                ),
              ),
              Text(
                '$itemCount articles',
                style: const TextStyle(
                  color: OvanieColors.navy,
                  fontSize: 10.2,
                ),
              ),
            ],
          ),
          const SizedBox(height: 9),
          _SummaryRow(label: 'Total produits', value: formatFcfa(subtotal)),
          const SizedBox(height: 7),
          _SummaryRow(
            label: 'Livraison',
            value: deliveryFee == null
                ? 'À confirmer'
                : formatFcfa(deliveryFee!),
          ),
          const SizedBox(height: 9),
          const Divider(height: 1),
          const SizedBox(height: 9),
          _SummaryRow(
            label: 'Total à payer',
            value: formatFcfa(finalTotal),
            strong: true,
          ),
        ],
      ),
    );
  }
}

class _SummaryRow extends StatelessWidget {
  final String label;
  final String value;
  final bool strong;
  final bool accent;

  const _SummaryRow({
    required this.label,
    required this.value,
    this.strong = false,
    this.accent = false,
  });

  @override
  Widget build(BuildContext context) => Row(
    children: [
      Expanded(
        child: Text(
          label,
          style: TextStyle(
            color: accent ? OvanieColors.orange : OvanieColors.navy,
            fontSize: strong ? 13 : 10.8,
            fontWeight: strong ? FontWeight.w900 : FontWeight.w500,
          ),
        ),
      ),
      const SizedBox(width: 8),
      FittedBox(
        fit: BoxFit.scaleDown,
        child: Text(
          value,
          style: TextStyle(
            color: strong || accent ? OvanieColors.orange : OvanieColors.navy,
            fontSize: strong ? 19 : 10.8,
            fontWeight: strong ? FontWeight.w900 : FontWeight.w500,
          ),
        ),
      ),
    ],
  );
}

class _DeliveryMetrics extends StatelessWidget {
  final CheckoutPreview? preview;
  const _DeliveryMetrics({required this.preview});

  @override
  Widget build(BuildContext context) {
    final weight = preview == null
        ? '—'
        : '${preview!.totalWeightKg.round()} kg';
    final vehicle = preview?.recommendedVehicle.trim().isNotEmpty == true
        ? preview!.recommendedVehicle
        : 'À confirmer';
    final zone = preview?.deliveryZone.trim().isNotEmpty == true
        ? preview!.deliveryZone
        : 'Abidjan';
    return Row(
      children: [
        _DeliveryMetric(
          icon: Icons.calendar_month_outlined,
          label: 'Livraison estimée',
          value: '24 à 48 h',
        ),
        const SizedBox(width: 7),
        _DeliveryMetric(
          icon: Icons.location_on_outlined,
          label: 'Zone',
          value: zone,
        ),
        const SizedBox(width: 7),
        _DeliveryMetric(
          icon: Icons.electric_rickshaw_outlined,
          label: 'Véhicule',
          value: vehicle,
        ),
        const SizedBox(width: 7),
        _DeliveryMetric(
          icon: Icons.scale_outlined,
          label: 'Poids total',
          value: weight,
        ),
      ],
    );
  }
}

class _DeliveryMetric extends StatelessWidget {
  final IconData icon;
  final String label;
  final String value;
  const _DeliveryMetric({
    required this.icon,
    required this.label,
    required this.value,
  });

  @override
  Widget build(BuildContext context) => Expanded(
    child: Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 8),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(8),
        border: Border.all(color: const Color(0xFFDDE3EC)),
      ),
      child: Row(
        children: [
          Icon(icon, color: OvanieColors.navy, size: 19),
          const SizedBox(width: 6),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  label,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    color: Color(0xFF59657B),
                    fontSize: 7.9,
                  ),
                ),
                const SizedBox(height: 2),
                Text(
                  value,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    color: OvanieColors.navy,
                    fontSize: 9.4,
                    fontWeight: FontWeight.w800,
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

class _ContactRow extends StatelessWidget {
  final IconData icon;
  final Color iconColor;
  final String label;
  final String value;
  final VoidCallback onEdit;

  const _ContactRow({
    required this.icon,
    required this.iconColor,
    required this.label,
    required this.value,
    required this.onEdit,
  });

  @override
  Widget build(BuildContext context) => Row(
    children: [
      Container(
        width: 39,
        height: 39,
        decoration: BoxDecoration(
          color: iconColor.withValues(alpha: .08),
          borderRadius: BorderRadius.circular(8),
        ),
        child: Icon(icon, color: iconColor, size: 22),
      ),
      const SizedBox(width: 10),
      Expanded(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              label,
              style: const TextStyle(color: Color(0xFF59657B), fontSize: 9.7),
            ),
            const SizedBox(height: 2),
            Text(
              value,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: const TextStyle(
                color: OvanieColors.navy,
                fontSize: 11.8,
                fontWeight: FontWeight.w700,
              ),
            ),
          ],
        ),
      ),
      TextButton.icon(
        onPressed: onEdit,
        style: TextButton.styleFrom(foregroundColor: OvanieColors.orange),
        iconAlignment: IconAlignment.end,
        icon: const Icon(Icons.chevron_right_rounded, size: 18),
        label: const Text('Modifier', style: TextStyle(fontSize: 10.5)),
      ),
    ],
  );
}

class _ReviewSummary extends StatelessWidget {
  final CheckoutPreview? preview;
  final int itemCount;
  const _ReviewSummary({required this.preview, required this.itemCount});

  @override
  Widget build(BuildContext context) {
    final subtotal = preview?.subtotal ?? 0;
    final delivery = preview?.deliveryFee ?? 0;
    final total = preview?.total ?? subtotal + delivery;
    final inferredDiscount = math.max(0.0, subtotal + delivery - total);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            const Expanded(
              child: Text('Récapitulatif', style: _CheckoutStyles.panelTitle),
            ),
            Text(
              '$itemCount articles',
              style: const TextStyle(color: OvanieColors.navy, fontSize: 10),
            ),
          ],
        ),
        const SizedBox(height: 12),
        _SummaryRow(label: 'Total produits', value: formatFcfa(subtotal)),
        const SizedBox(height: 9),
        _SummaryRow(label: 'Livraison', value: formatFcfa(delivery)),
        if (inferredDiscount > 0) ...[
          const SizedBox(height: 9),
          _SummaryRow(
            label: 'Remise',
            value: '-${formatFcfa(inferredDiscount)}',
            accent: true,
          ),
        ],
        const SizedBox(height: 9),
        const Divider(height: 1),
        const SizedBox(height: 11),
        _SummaryRow(
          label: 'Total à payer',
          value: formatFcfa(total),
          strong: true,
        ),
      ],
    );
  }
}

class _TrustStrip extends StatelessWidget {
  const _TrustStrip();

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 11),
    decoration: BoxDecoration(
      color: Colors.white,
      borderRadius: BorderRadius.circular(9),
      border: Border.all(color: const Color(0xFFE0E5EC)),
    ),
    child: const Row(
      children: [
        _TrustItem(
          icon: Icons.shield_outlined,
          title: 'Paiement sécurisé',
          subtitle: 'Transactions 100% sûres',
        ),
        _MiniDivider(),
        _TrustItem(
          icon: Icons.local_shipping_outlined,
          title: 'Livraison fiable',
          subtitle: 'Suivi et respect des délais',
        ),
        _MiniDivider(),
        _TrustItem(
          icon: Icons.headset_mic_outlined,
          title: 'Support dédié',
          subtitle: '7j/7 à votre écoute',
        ),
      ],
    ),
  );
}

class _TrustItem extends StatelessWidget {
  final IconData icon;
  final String title;
  final String subtitle;
  const _TrustItem({
    required this.icon,
    required this.title,
    required this.subtitle,
  });

  @override
  Widget build(BuildContext context) => Expanded(
    child: Row(
      children: [
        Icon(icon, color: OvanieColors.navy, size: 22),
        const SizedBox(width: 7),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                title,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(
                  color: OvanieColors.navy,
                  fontSize: 9.2,
                  fontWeight: FontWeight.w800,
                ),
              ),
              const SizedBox(height: 1),
              Text(
                subtitle,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(color: Color(0xFF5A667B), fontSize: 7.8),
              ),
            ],
          ),
        ),
      ],
    ),
  );
}

class _PaymentChoiceCard extends StatelessWidget {
  final String title;
  final String subtitle;
  final List<String> bullets;
  final IconData icon;
  final bool selected;
  final bool enabled;
  final bool online;
  final VoidCallback onTap;

  const _PaymentChoiceCard({
    required this.title,
    required this.subtitle,
    required this.bullets,
    required this.icon,
    required this.selected,
    required this.enabled,
    required this.online,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    final borderColor = selected
        ? OvanieColors.orange
        : const Color(0xFFDCE2EB);
    final checkColor = selected ? OvanieColors.orange : const Color(0xFF98A4B7);

    return InkWell(
      onTap: enabled ? onTap : null,
      borderRadius: BorderRadius.circular(11),
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 160),
        constraints: const BoxConstraints(minHeight: 268),
        padding: const EdgeInsets.fromLTRB(14, 18, 14, 16),
        decoration: BoxDecoration(
          color: selected ? const Color(0xFFFFFBF8) : Colors.white,
          borderRadius: BorderRadius.circular(11),
          border: Border.all(color: borderColor, width: selected ? 1.35 : 1),
        ),
        child: Stack(
          children: [
            Positioned(
              top: 0,
              right: 0,
              child: Icon(
                selected
                    ? Icons.radio_button_checked_rounded
                    : Icons.radio_button_off_rounded,
                color: selected ? OvanieColors.orange : const Color(0xFF9AA5B5),
                size: 24,
              ),
            ),
            Opacity(
              opacity: enabled ? 1 : .45,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.center,
                children: [
                  Center(
                    child: Container(
                      width: 72,
                      height: 72,
                      decoration: BoxDecoration(
                        color: online
                            ? const Color(0xFFF3F6FF)
                            : const Color(0xFFFFF3EB),
                        shape: BoxShape.circle,
                      ),
                      child: Stack(
                        alignment: Alignment.center,
                        children: [
                          Icon(
                            icon,
                            color: online
                                ? const Color(0xFF244DD8)
                                : OvanieColors.orange,
                            size: 37,
                          ),
                        ],
                      ),
                    ),
                  ),
                  const SizedBox(height: 10),
                  Text(
                    title,
                    textAlign: TextAlign.center,
                    maxLines: 2,
                    style: const TextStyle(
                      color: OvanieColors.navy,
                      fontSize: 14.7,
                      height: 1.15,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                  const SizedBox(height: 8),
                  Text(
                    subtitle,
                    textAlign: TextAlign.center,
                    maxLines: 3,
                    style: const TextStyle(
                      color: OvanieColors.navy,
                      fontSize: 10.3,
                      height: 1.35,
                    ),
                  ),
                  const SizedBox(height: 14),
                  for (final bullet in bullets)
                    Padding(
                      padding: const EdgeInsets.only(bottom: 8),
                      child: Row(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Icon(
                            Icons.check_circle_outline_rounded,
                            color: checkColor,
                            size: 16,
                          ),
                          const SizedBox(width: 7),
                          Expanded(
                            child: Text(
                              bullet,
                              style: const TextStyle(
                                color: OvanieColors.navy,
                                fontSize: 9.2,
                                height: 1.35,
                              ),
                            ),
                          ),
                        ],
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
}

class _PaymentOrderSummary extends StatelessWidget {
  final CheckoutPreview? preview;
  final int itemCount;

  const _PaymentOrderSummary({required this.preview, required this.itemCount});

  @override
  Widget build(BuildContext context) {
    final subtotal = preview?.subtotal ?? CartStore.instance.subtotal;
    final delivery = preview?.deliveryFee ?? 0;
    final total = preview?.total ?? subtotal + delivery;
    final discount = math.max(0.0, subtotal + delivery - total);

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            const Expanded(
              child: Text(
                'Récapitulatif de la commande',
                style: _CheckoutStyles.panelTitle,
              ),
            ),
            const Text(
              'Voir détails',
              style: TextStyle(
                color: OvanieColors.orange,
                fontSize: 10.3,
                fontWeight: FontWeight.w700,
              ),
            ),
            const SizedBox(width: 2),
            const Icon(
              Icons.keyboard_arrow_down_rounded,
              color: OvanieColors.orange,
              size: 18,
            ),
          ],
        ),
        const SizedBox(height: 14),
        _SummaryRow(
          label: 'Sous-total ($itemCount articles)',
          value: formatFcfa(subtotal),
        ),
        const SizedBox(height: 10),
        _SummaryRow(label: 'Livraison', value: formatFcfa(delivery)),
        if (discount > 0) ...[
          const SizedBox(height: 10),
          _SummaryRow(
            label: 'Remise',
            value: '-${formatFcfa(discount)}',
            accent: true,
          ),
        ],
        const SizedBox(height: 11),
        const Divider(height: 1),
        const SizedBox(height: 12),
        _SummaryRow(
          label: 'Total à payer',
          value: formatFcfa(total),
          strong: true,
        ),
      ],
    );
  }
}

class _PaymentStageBottomBar extends StatelessWidget {
  final bool loading;
  final String label;
  final VoidCallback? onPressed;

  const _PaymentStageBottomBar({
    required this.loading,
    required this.label,
    required this.onPressed,
  });

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.fromLTRB(18, 9, 18, 12),
    decoration: const BoxDecoration(
      color: Colors.white,
      border: Border(top: BorderSide(color: Color(0xFFE6E9EF))),
      boxShadow: [
        BoxShadow(
          color: Color(0x10000000),
          blurRadius: 14,
          offset: Offset(0, -4),
        ),
      ],
    ),
    child: SizedBox(
      height: 54,
      child: FilledButton.icon(
        onPressed: loading ? null : onPressed,
        style: FilledButton.styleFrom(
          backgroundColor: const Color(0xFFFF3A0B),
          foregroundColor: Colors.white,
          disabledBackgroundColor: const Color(0xFFFFA68D),
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(9)),
        ),
        icon: loading
            ? const SizedBox.square(
                dimension: 18,
                child: CircularProgressIndicator(
                  strokeWidth: 2,
                  color: Colors.white,
                ),
              )
            : const Icon(Icons.arrow_forward_rounded),
        label: Text(
          label,
          style: const TextStyle(fontSize: 15, fontWeight: FontWeight.w900),
        ),
      ),
    ),
  );
}

class _CheckoutBottomBar extends StatelessWidget {
  final double amount;
  final bool loading;
  final String label;
  final VoidCallback onPressed;
  final bool secure;

  const _CheckoutBottomBar({
    required this.amount,
    required this.loading,
    required this.label,
    required this.onPressed,
    this.secure = false,
  });

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.fromLTRB(18, 11, 18, 12),
    decoration: const BoxDecoration(
      color: Colors.white,
      border: Border(top: BorderSide(color: Color(0xFFE6E9EF))),
      boxShadow: [
        BoxShadow(
          color: Color(0x10000000),
          blurRadius: 14,
          offset: Offset(0, -4),
        ),
      ],
    ),
    child: Row(
      children: [
        SizedBox(
          width: 145,
          child: FittedBox(
            fit: BoxFit.scaleDown,
            alignment: Alignment.centerLeft,
            child: Text(
              formatFcfa(amount),
              style: const TextStyle(
                color: OvanieColors.orange,
                fontSize: 24,
                fontWeight: FontWeight.w900,
              ),
            ),
          ),
        ),
        const SizedBox(width: 14),
        Expanded(
          child: SizedBox(
            height: 52,
            child: FilledButton.icon(
              onPressed: loading ? null : onPressed,
              style: FilledButton.styleFrom(
                backgroundColor: const Color(0xFFFF3A0B),
                foregroundColor: Colors.white,
                disabledBackgroundColor: const Color(0xFFFFA68D),
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(9),
                ),
              ),
              icon: loading
                  ? const SizedBox.square(
                      dimension: 18,
                      child: CircularProgressIndicator(
                        strokeWidth: 2,
                        color: Colors.white,
                      ),
                    )
                  : const Icon(Icons.arrow_forward_rounded),
              label: Text(
                label,
                style: const TextStyle(
                  fontSize: 14.5,
                  fontWeight: FontWeight.w900,
                ),
              ),
            ),
          ),
        ),
      ],
    ),
  );
}

class _CheckoutError extends StatelessWidget {
  final String message;
  const _CheckoutError({required this.message});

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(11),
    decoration: BoxDecoration(
      color: const Color(0xFFFFF2F0),
      borderRadius: BorderRadius.circular(8),
      border: Border.all(color: const Color(0xFFFFC7BC)),
    ),
    child: Row(
      children: [
        const Icon(
          Icons.error_outline_rounded,
          color: OvanieColors.orange,
          size: 20,
        ),
        const SizedBox(width: 9),
        Expanded(
          child: Text(
            message,
            style: const TextStyle(color: Color(0xFF8B3122), fontSize: 10.5),
          ),
        ),
      ],
    ),
  );
}

class _OnlineOperatorTile extends StatelessWidget {
  final CheckoutPaymentOperatorOption operator;
  final bool selected;
  final bool enabled;
  final VoidCallback onTap;

  const _OnlineOperatorTile({
    required this.operator,
    required this.selected,
    required this.enabled,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    String? asset;
    String subtitle = 'Paiement sécurisé';
    switch (operator.code) {
      case 'wave':
        asset = 'assets/images/operators/wave.png';
        subtitle = 'Payez avec votre compte Wave';
        break;
      case 'orange':
        asset = 'assets/images/operators/orange.png';
        subtitle = 'Payez avec Orange Money';
        break;
      case 'mtn':
        asset = 'assets/images/operators/mtn.png';
        subtitle = 'Payez avec MTN Mobile Money';
        break;
      case 'moov':
        asset = 'assets/images/operators/moov.png';
        subtitle = 'Payez avec Moov Money';
        break;
      case 'card':
        subtitle = 'Visa, Mastercard et autres';
        break;
    }
    return InkWell(
      onTap: enabled ? onTap : null,
      borderRadius: BorderRadius.circular(8),
      child: Container(
        constraints: const BoxConstraints(minHeight: 58),
        padding: const EdgeInsets.symmetric(horizontal: 11, vertical: 9),
        decoration: BoxDecoration(
          color: enabled ? Colors.white : const Color(0xFFF7F8FA),
          borderRadius: BorderRadius.circular(8),
          border: Border.all(
            color: selected ? OvanieColors.orange : const Color(0xFFE0E5EC),
          ),
        ),
        child: Row(
          children: [
            SizedBox(
              width: 64,
              height: 38,
              child: asset == null
                  ? const Icon(
                      Icons.credit_card_rounded,
                      color: Color(0xFF0B4AB8),
                      size: 31,
                    )
                  : Image.asset(asset, fit: BoxFit.contain),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    operator.label,
                    style: const TextStyle(
                      color: OvanieColors.navy,
                      fontSize: 11.5,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                  const SizedBox(height: 3),
                  Text(
                    subtitle,
                    style: const TextStyle(
                      color: Color(0xFF59657B),
                      fontSize: 9.7,
                    ),
                  ),
                ],
              ),
            ),
            if (operator.code == 'card') ...[
              const _VisaBadge(),
              const SizedBox(width: 7),
              const _MastercardBadge(),
              const SizedBox(width: 10),
            ],
            Icon(
              selected ? Icons.radio_button_checked : Icons.radio_button_off,
              color: selected ? OvanieColors.orange : const Color(0xFF9AA5B5),
            ),
          ],
        ),
      ),
    );
  }
}

class _VisaBadge extends StatelessWidget {
  const _VisaBadge();

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 4),
    decoration: BoxDecoration(
      color: Colors.white,
      borderRadius: BorderRadius.circular(5),
      border: Border.all(color: const Color(0xFFE1E5EC)),
    ),
    child: const Text(
      'VISA',
      style: TextStyle(
        color: Color(0xFF1434CB),
        fontSize: 10.5,
        fontWeight: FontWeight.w900,
        fontStyle: FontStyle.italic,
      ),
    ),
  );
}

class _MastercardBadge extends StatelessWidget {
  const _MastercardBadge();

  @override
  Widget build(BuildContext context) => SizedBox(
    width: 31,
    height: 22,
    child: Stack(
      alignment: Alignment.center,
      children: [
        Positioned(
          left: 2,
          child: Container(
            width: 19,
            height: 19,
            decoration: const BoxDecoration(
              color: Color(0xFFEB001B),
              shape: BoxShape.circle,
            ),
          ),
        ),
        Positioned(
          right: 2,
          child: Container(
            width: 19,
            height: 19,
            decoration: const BoxDecoration(
              color: Color(0xFFF79E1B),
              shape: BoxShape.circle,
            ),
          ),
        ),
      ],
    ),
  );
}

class _PaymentWaitingScreen extends StatefulWidget {
  final int orderId;
  final String orderNumber;
  final double amount;
  final String methodLabel;
  final String paymentUrl;

  const _PaymentWaitingScreen({
    required this.orderId,
    required this.orderNumber,
    required this.amount,
    required this.methodLabel,
    required this.paymentUrl,
  });

  @override
  State<_PaymentWaitingScreen> createState() => _PaymentWaitingScreenState();
}

class _PaymentWaitingScreenState extends State<_PaymentWaitingScreen>
    with WidgetsBindingObserver {
  Timer? _timer;
  bool _checking = false;
  bool _providerReturned = false;
  bool _waiting = true;
  int _attempts = 0;
  int _attemptsAfterReturn = 0;
  String _message =
      'Terminez le paiement dans la page sécurisée, puis revenez dans OVANIE.';

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _startPolling();
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    _timer?.cancel();
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) {
      _providerReturned = true;
      _attemptsAfterReturn = 0;
      if (mounted) {
        setState(() {
          _waiting = true;
          _message = 'Vérification de la réponse du prestataire…';
        });
      }
      _startPolling();
      unawaited(_check());
    }
  }

  void _startPolling() {
    _timer?.cancel();
    _timer = Timer.periodic(
      const Duration(seconds: 3),
      (_) => unawaited(_check()),
    );
    unawaited(_check());
  }

  void _stopAsPending() {
    _timer?.cancel();
    if (!mounted) return;
    setState(() {
      _waiting = false;
      _message =
          'Aucune confirmation de paiement n’a été reçue. Aucun succès n’est affiché tant que PayDunya ne l’a pas confirmé. Vous pouvez reprendre le paiement ou vérifier à nouveau le statut.';
    });
  }

  Future<void> _check() async {
    if (_checking) return;
    _checking = true;
    try {
      final order = await const OrdersRepository().fetchOrder(widget.orderId);
      if (!mounted) return;

      if (order.isPaymentConfirmed) {
        _timer?.cancel();
        await CartStore.instance.clearLocalMirrorOnly();
        try {
          await const CartApiRepository().refreshLocalCartFromServer();
        } catch (_) {}
        if (!mounted) return;
        await Navigator.of(context).pushReplacement(
          MaterialPageRoute<void>(
            builder: (_) => OrderConfirmationScreen(
              orderId: order.id,
              initialOrderNumber: order.orderNumber,
              initialAmount: order.total,
              initialPaymentMethod: widget.methodLabel,
              returnState: 'completed',
            ),
          ),
        );
        return;
      }

      if (order.isPaymentFailed || order.isPaymentCancelled) {
        _timer?.cancel();
        await Navigator.of(context).pushReplacement(
          MaterialPageRoute<void>(
            builder: (_) => OrderConfirmationScreen(
              orderId: order.id,
              initialOrderNumber: order.orderNumber,
              initialAmount: order.total,
              initialPaymentMethod: widget.methodLabel,
              returnState: order.isPaymentCancelled ? 'cancelled' : 'failed',
            ),
          ),
        );
        return;
      }

      _attempts++;
      if (_providerReturned) _attemptsAfterReturn++;

      // Un retour manuel depuis Chrome sans paiement ne doit jamais produire
      // une roue infinie. Après quelques vérifications le statut reste « en
      // attente » et l'utilisateur peut relancer la page PayDunya.
      if ((_providerReturned && _attemptsAfterReturn >= 5) || _attempts >= 20) {
        _stopAsPending();
      }
    } catch (_) {
      _attempts++;
      if (_providerReturned) _attemptsAfterReturn++;
      if (!mounted) return;
      if ((_providerReturned && _attemptsAfterReturn >= 5) || _attempts >= 20) {
        _timer?.cancel();
        setState(() {
          _waiting = false;
          _message =
              'La connexion est instable et le paiement n’a pas pu être confirmé. Votre commande reste protégée : vérifiez le statut ou reprenez le paiement.';
        });
      } else if (_attempts > 2) {
        setState(() {
          _message =
              'Connexion temporairement instable. OVANIE continue de vérifier le statut côté serveur.';
        });
      }
    } finally {
      _checking = false;
    }
  }

  Future<void> _resumePayment() async {
    final url = widget.paymentUrl.trim();
    if (url.isEmpty) return;
    await InAppPaymentScreen.open(context, url);
    if (!mounted) return;
    _providerReturned = false;
    _attempts = 0;
    _attemptsAfterReturn = 0;
    setState(() {
      _waiting = true;
      _message =
          'Terminez le paiement dans la page sécurisée, puis revenez dans OVANIE.';
    });
    _startPolling();
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    backgroundColor: Colors.white,
    body: SafeArea(
      child: ListView(
        padding: const EdgeInsets.fromLTRB(18, 0, 18, 24),
        children: [
          _CheckoutPageHeader(
            current: 2,
            completedThrough: 1,
            onBack: () => Navigator.of(context).maybePop(),
          ),
          const SizedBox(height: 40),
          Center(
            child: AnimatedSwitcher(
              duration: const Duration(milliseconds: 220),
              child: _waiting
                  ? const SizedBox(
                      key: ValueKey('loading'),
                      width: 58,
                      height: 58,
                      child: CircularProgressIndicator(
                        strokeWidth: 5,
                        color: OvanieColors.orange,
                      ),
                    )
                  : Container(
                      key: const ValueKey('pending'),
                      width: 64,
                      height: 64,
                      decoration: const BoxDecoration(
                        color: Color(0xFFFFF3EA),
                        shape: BoxShape.circle,
                      ),
                      child: const Icon(
                        Icons.hourglass_bottom_rounded,
                        color: OvanieColors.orange,
                        size: 34,
                      ),
                    ),
            ),
          ),
          const SizedBox(height: 24),
          Text(
            _waiting ? 'Vérification du paiement' : 'Paiement non finalisé',
            textAlign: TextAlign.center,
            style: const TextStyle(
              color: OvanieColors.navy,
              fontSize: 22,
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 9),
          Text(
            _message,
            textAlign: TextAlign.center,
            style: const TextStyle(
              color: Color(0xFF59657B),
              fontSize: 12.5,
              height: 1.4,
            ),
          ),
          const SizedBox(height: 22),
          _CheckoutPanel(
            child: Column(
              children: [
                _SummaryRow(label: 'Commande', value: widget.orderNumber),
                const SizedBox(height: 9),
                _SummaryRow(label: 'Montant', value: formatFcfa(widget.amount)),
                const SizedBox(height: 9),
                _SummaryRow(label: 'Méthode', value: widget.methodLabel),
              ],
            ),
          ),
          const SizedBox(height: 16),
          if (!_waiting) ...[
            SizedBox(
              height: 50,
              child: FilledButton.icon(
                onPressed: _resumePayment,
                style: FilledButton.styleFrom(
                  backgroundColor: OvanieColors.orange,
                  foregroundColor: Colors.white,
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(9),
                  ),
                ),
                icon: const Icon(Icons.open_in_browser_rounded),
                label: const Text(
                  'Reprendre le paiement',
                  style: TextStyle(fontWeight: FontWeight.w900),
                ),
              ),
            ),
            const SizedBox(height: 10),
          ],
          SizedBox(
            height: 50,
            child: OutlinedButton.icon(
              onPressed: _checking ? null : _check,
              style: OutlinedButton.styleFrom(
                foregroundColor: OvanieColors.navy,
                side: const BorderSide(color: OvanieColors.navy),
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(9),
                ),
              ),
              icon: const Icon(Icons.refresh_rounded),
              label: const Text(
                'Vérifier le statut maintenant',
                style: TextStyle(fontWeight: FontWeight.w800),
              ),
            ),
          ),
        ],
      ),
    ),
  );
}

class _AddressFormResult {
  final String name;
  final String phone;
  final String zone;
  final String commune;
  final String quartier;
  final String city;
  final String address;
  final String notes;
  final double? latitude;
  final double? longitude;
  final double? geoAccuracyMeters;
  final int? savedAddressId;
  final String geoSource;
  final String localityType;

  const _AddressFormResult({
    required this.name,
    required this.phone,
    required this.zone,
    required this.commune,
    required this.quartier,
    required this.city,
    required this.address,
    required this.notes,
    this.latitude,
    this.longitude,
    this.geoAccuracyMeters,
    this.savedAddressId,
    this.geoSource = 'mobile_manual',
    this.localityType = '',
  });
}

class _AddressSheet extends StatefulWidget {
  final _AddressFormResult initial;
  final List<CheckoutSavedAddress> savedAddresses;
  final bool loadingSavedAddresses;

  const _AddressSheet({
    required this.initial,
    required this.savedAddresses,
    required this.loadingSavedAddresses,
  });

  @override
  State<_AddressSheet> createState() => _AddressSheetState();
}

class _AddressSheetState extends State<_AddressSheet> {
  static const double _addressLookupMaxAccuracyMeters = 500;

  final _formKey = GlobalKey<FormState>();
  final _geoRepository = const GeoRepository();

  late final TextEditingController _name;
  late final TextEditingController _phone;
  late final TextEditingController _search;

  late String _zone;
  late String _commune;
  late String _quartier;
  late String _city;
  late String _address;
  late String _notes;
  late String _geoSource;
  late String _localityType;
  double? _latitude;
  double? _locationAccuracyMeters;
  double? _longitude;
  int? _savedAddressId;

  Timer? _searchTimer;
  int _searchRequestId = 0;
  bool _searching = false;
  bool _locating = false;
  String _geoStatus = '';
  String? _locationError;
  List<GeoSearchResult> _searchResults = const [];

  StreamSubscription<DevicePosition>? _livePositionSubscription;
  double? _lastTrackedLatitude;
  double? _lastTrackedLongitude;
  DateTime? _lastReverseAt;
  int _liveReverseRequestId = 0;

  @override
  void initState() {
    super.initState();
    _name = TextEditingController(text: widget.initial.name);
    _phone = TextEditingController(
      text: formatCiPhoneDisplay(widget.initial.phone),
    );
    _search = TextEditingController(text: widget.initial.address);
    _zone = widget.initial.zone;
    _commune = widget.initial.commune;
    _quartier = widget.initial.quartier;
    _city = widget.initial.city;
    _address = widget.initial.address;
    _notes = widget.initial.notes;
    _latitude = widget.initial.latitude;
    _longitude = widget.initial.longitude;
    _savedAddressId = widget.initial.savedAddressId;
    _geoSource = widget.initial.geoSource;
    _localityType = widget.initial.localityType;
    _locationAccuracyMeters = widget.initial.geoAccuracyMeters;
  }

  @override
  void dispose() {
    _searchTimer?.cancel();
    _livePositionSubscription?.cancel();
    _livePositionSubscription = null;
    _name.dispose();
    _phone.dispose();
    _search.dispose();
    super.dispose();
  }

  String? _required(String? value, String message) {
    return (value ?? '').trim().isEmpty ? message : null;
  }

  bool _looksLikeDetailedAddress(String value) {
    final clean = value.trim();
    if (clean.isEmpty) return false;
    final lower = clean.toLowerCase();
    if (lower == 'position actuelle détectée' ||
        lower == 'position actuelle detectee' ||
        lower == 'position actuelle') {
      return false;
    }
    if (_quartier.trim().isNotEmpty) return true;
    if (RegExp(
      r'\b(rue|avenue|boulevard|route|cité|cite|résidence|residence|carrefour|lot|immeuble|école|ecole|hôpital|hopital|marché|marche)\b',
      caseSensitive: false,
    ).hasMatch(clean)) {
      return true;
    }
    return clean.split(',').where((part) => part.trim().isNotEmpty).length >= 4;
  }

  /// Une adresse issue de « Ma position » est déjà une sélection réelle :
  /// les coordonnées GPS sont conservées séparément et Laravel ne fait que
  /// donner un libellé humain. Il ne faut donc pas rejeter « Feh Kessé,
  /// Abidjan, Côte d'Ivoire » simplement parce qu'il n'y a ni mot « rue » ni
  /// quatre segments séparés par des virgules.
  bool _looksLikeUsableGpsAddress(String value) {
    final clean = value.trim();
    if (clean.isEmpty) return false;

    final lower = clean.toLowerCase();
    if (lower == 'position actuelle détectée' ||
        lower == 'position actuelle detectee' ||
        lower == 'position actuelle') {
      return false;
    }

    if (_quartier.trim().isNotEmpty) return true;

    if (RegExp(
      r'\b(rue|avenue|boulevard|route|cité|cite|résidence|residence|carrefour|lot|immeuble|école|ecole|hôpital|hopital|marché|marche)\b',
      caseSensitive: false,
    ).hasMatch(clean)) {
      return true;
    }

    // Un nom de quartier/lieu + ville + pays est suffisant pour confirmer le
    // résultat de « Ma position ». Exemple :
    // « Feh Kessé, Abidjan, Côte d'Ivoire ».
    final parts = clean
        .split(',')
        .map((part) => part.trim())
        .where((part) => part.isNotEmpty)
        .toList(growable: false);
    if (parts.length >= 3) {
      final first = parts.first.toLowerCase();
      const genericFirstParts = <String>{
        'abidjan',
        "côte d'ivoire",
        "cote d'ivoire",
      };
      return !genericFirstParts.contains(first);
    }

    return false;
  }

  bool get _hasSelectedLocation {
    final address = _address.trim();
    final commune = _commune.trim();
    final gpsSource =
        _geoSource == 'mobile_live_gps' ||
        _geoSource == 'mobile_location_assist';

    final coordinatesOk =
        _latitude != null &&
        _longitude != null &&
        _latitude!.isFinite &&
        _longitude!.isFinite &&
        (_latitude!.abs() > 0 || _longitude!.abs() > 0);

    // V67 : lorsque « Ma position » a réellement retourné des coordonnées et
    // qu'un reverse-géocodeur a affiché une rue/adresse lisible, cette adresse
    // EST sélectionnée. Certains géocodeurs Android renvoient par exemple
    // « 427 Rue ..., Abidjan, Côte d'Ivoire » sans découper la commune dans un
    // champ séparé. L'ancienne validation rejetait alors l'adresse visible et
    // affichait à tort « choisissez une adresse proposée ».
    if (gpsSource) {
      final accuracy = _locationAccuracyMeters;
      if (!coordinatesOk ||
          accuracy == null ||
          !accuracy.isFinite ||
          accuracy <= 0 ||
          address.isEmpty ||
          !_looksLikeUsableGpsAddress(address)) {
        return false;
      }

      if (_geoSource == 'mobile_live_gps' &&
          accuracy > DeviceLocation.maxAcceptedAccuracyMeters) {
        return false;
      }
      if (_geoSource == 'mobile_location_assist' &&
          accuracy > _addressLookupMaxAccuracyMeters) {
        return false;
      }

      return true;
    }

    final adminOk =
        (_zone == 'abidjan' &&
            commune.isNotEmpty &&
            commune.toLowerCase() != 'abidjan') ||
        (_zone == 'interieur' && _city.trim().isNotEmpty);

    if (!adminOk || !_looksLikeDetailedAddress(address)) {
      // Une adresse saisie/sélectionnée manuellement peut rester valable même
      // si le fournisseur n'a pas découpé le quartier dans un champ séparé.
      return adminOk && address.isNotEmpty;
    }
    return true;
  }

  void _applySavedAddress(int id) {
    _stopLiveTracking();
    CheckoutSavedAddress? selected;
    for (final item in widget.savedAddresses) {
      if (item.id == id) {
        selected = item;
        break;
      }
    }
    if (selected == null) return;
    final safeSelected = selected;

    final place =
        '${safeSelected.commune} ${safeSelected.city} ${safeSelected.address}'
            .toLowerCase();
    final zone =
        place.contains('abidjan') ||
            (safeSelected.commune.isNotEmpty && safeSelected.city.isEmpty)
        ? 'abidjan'
        : 'interieur';

    setState(() {
      _savedAddressId = safeSelected.id;
      if (safeSelected.recipientName.isNotEmpty) {
        _name.text = safeSelected.recipientName;
      }
      if (safeSelected.phone.isNotEmpty) _phone.text = safeSelected.phone;
      _zone = zone;
      _commune = safeSelected.commune;
      _quartier = safeSelected.sousQuartier.isNotEmpty
          ? safeSelected.sousQuartier
          : safeSelected.quartier;
      _city = safeSelected.city;
      _address = safeSelected.address;
      _search.text = safeSelected.address;
      _latitude = safeSelected.latitude;
      _longitude = safeSelected.longitude;
      _geoSource = 'address_book';
      _localityType = safeSelected.localityType;
      _locationAccuracyMeters = null;
      _locationError = null;
      _searchResults = const [];
      _geoStatus = 'Adresse enregistrée sélectionnée.';
    });
  }

  void _onSearchChanged(String value) {
    _stopLiveTracking();
    _searchTimer?.cancel();
    final query = value.trim();

    if (query != _address.trim()) {
      setState(() {
        _address = '';
        _commune = '';
        _quartier = '';
        _city = '';
        _latitude = null;
        _longitude = null;
        _savedAddressId = null;
        _geoSource = 'mobile_manual';
        _localityType = '';
        _locationAccuracyMeters = null;
        _locationError = null;
      });
    }

    if (query.length < 4) {
      setState(() {
        _searchResults = const [];
        _searching = false;
      });
      return;
    }

    final requestId = ++_searchRequestId;
    _searchTimer = Timer(const Duration(milliseconds: 350), () {
      _runAddressSearch(query, requestId);
    });
  }

  Future<void> _runAddressSearch(String query, int requestId) async {
    if (!mounted) return;
    setState(() {
      _searching = true;
      _locationError = null;
    });

    try {
      final results = await _geoRepository.search(query);
      if (!mounted || requestId != _searchRequestId) return;
      setState(() {
        _searchResults = results;
        _searching = false;
      });
    } catch (error) {
      if (!mounted || requestId != _searchRequestId) return;
      setState(() {
        _searchResults = const [];
        _searching = false;
        _locationError = ApiClient.friendlyError(error);
      });
    }
  }

  Future<void> _selectSearchResult(GeoSearchResult result) async {
    _stopLiveTracking();
    setState(() {
      _locating = true;
      _searchResults = const [];
      _geoStatus = 'Identification de l’adresse…';
      _locationError = null;
    });

    try {
      final place = await _geoRepository.reverse(
        latitude: result.latitude,
        longitude: result.longitude,
        fallbackDisplayName: result.displayName,
      );
      if (!mounted) return;
      _applyResolvedPlace(place, source: 'geocoding');
      setState(() => _geoStatus = 'Adresse sélectionnée.');
    } catch (_) {
      if (!mounted) return;
      final fallback = _fallbackPlaceFromDisplayName(result);
      _applyResolvedPlace(fallback, source: 'geocoding');
      setState(() {
        _geoStatus = 'Adresse sélectionnée.';
      });
    } finally {
      if (mounted) setState(() => _locating = false);
    }
  }

  GeoResolvedPlace _fallbackPlaceFromDisplayName(GeoSearchResult result) {
    final display = result.displayName.trim();
    final lower = display.toLowerCase();
    final firstPart = display.split(',').first.trim();
    final firstLower = firstPart.toLowerCase();
    final isAbidjan =
        lower.contains('abidjan') ||
        firstLower == 'bingerville' ||
        firstLower == 'anyama' ||
        firstLower == 'songon';
    return GeoResolvedPlace(
      displayName: display,
      zone: isAbidjan ? 'abidjan' : 'interieur',
      commune: isAbidjan ? firstPart : firstPart,
      quartier: '',
      city: isAbidjan ? 'Abidjan' : firstPart,
      latitude: result.latitude,
      longitude: result.longitude,
    );
  }

  String _sourceForLocationAccuracy(double? accuracy, {bool emulator = false}) {
    if (emulator) return 'mobile_live_gps';
    if (accuracy != null &&
        accuracy.isFinite &&
        accuracy > 0 &&
        accuracy <= DeviceLocation.maxAcceptedAccuracyMeters) {
      return 'mobile_live_gps';
    }
    return 'mobile_location_assist';
  }

  Future<void> _useCurrentPosition() async {
    if (_locating) return;

    _stopLiveTracking();
    setState(() {
      _locating = true;
      _searchResults = const [];
      _locationError = null;
      _address = '';
      _commune = '';
      _quartier = '';
      _city = '';
      _latitude = null;
      _longitude = null;
      _savedAddressId = null;
      _geoSource = 'mobile_location_assist';
      _geoStatus = '';
    });

    try {
      final position = await DeviceLocation.currentPosition();
      if (kDebugMode) {
        debugPrint(
          '[OVANIE GEO] initial lat=${position.latitude} '
          'lng=${position.longitude} accuracy=${position.accuracy}',
        );
      }
      if (!mounted) return;

      final accuracy = position.accuracy;
      final source = _sourceForLocationAccuracy(
        accuracy,
        emulator: position.isEmulator,
      );

      setState(() {
        _locationAccuracyMeters = accuracy;
        _latitude = position.latitude;
        _longitude = position.longitude;
        _geoSource = source;
        _geoStatus = '';
      });

      _lastTrackedLatitude = position.latitude;
      _lastTrackedLongitude = position.longitude;
      _lastReverseAt = null;

      // V65 : ne plus bloquer l'affichage de l'adresse jusqu'à <=15 m.
      // Une position <=500 m peut servir à retrouver une adresse lisible,
      // tandis que le flux Fused continue silencieusement à améliorer le point.
      if (position.isEmulator ||
          (accuracy != null &&
              accuracy.isFinite &&
              accuracy > 0 &&
              accuracy <= _addressLookupMaxAccuracyMeters)) {
        try {
          final place = await _geoRepository.reverse(
            latitude: position.latitude,
            longitude: position.longitude,
            fresh: true,
          );
          if (!mounted) return;

          _applyResolvedPlace(
            place,
            source: source,
            exactLatitude: position.latitude,
            exactLongitude: position.longitude,
          );
          _lastReverseAt = DateTime.now();
          setState(() {
            _geoStatus = '';
            _locationError = null;
          });
        } catch (_) {
          if (mounted) {
            setState(() {
              _geoStatus = '';
              _locationError = null;
            });
          }
        }
      }

      _startLiveTracking();
    } on DeviceLocationException catch (error) {
      if (!mounted) return;
      setState(() {
        _locationError = error.message;
        _geoStatus = '';
      });
    } on TimeoutException {
      if (!mounted) return;
      setState(() {
        _locationError =
            'Nous n’avons pas pu détecter votre adresse. Saisissez-la manuellement.';
        _geoStatus = '';
      });
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _locationError =
            'Nous n’avons pas pu détecter votre adresse. Saisissez-la manuellement.';
        _geoStatus = '';
      });
    } finally {
      if (mounted) setState(() => _locating = false);
    }
  }

  void _startLiveTracking() {
    _livePositionSubscription?.cancel();
    _livePositionSubscription =
        DeviceLocation.positionStream(distanceFilterMeters: 0).listen(
          (position) => unawaited(_applyLivePosition(position)),
          onError: (Object error) {
            if (!mounted ||
                (_geoSource != 'mobile_live_gps' &&
                    _geoSource != 'mobile_location_assist')) {
              return;
            }
            setState(() {
              _geoStatus = '';
            });
          },
        );
  }

  void _stopLiveTracking() {
    _livePositionSubscription?.cancel();
    _livePositionSubscription = null;
    _liveReverseRequestId++;
    _lastTrackedLatitude = null;
    _lastTrackedLongitude = null;
    _lastReverseAt = null;
  }

  Future<void> _applyLivePosition(DevicePosition position) async {
    if (!mounted ||
        (_geoSource != 'mobile_live_gps' &&
            _geoSource != 'mobile_location_assist')) {
      return;
    }

    if (kDebugMode) {
      debugPrint(
        '[OVANIE GEO] live lat=${position.latitude} '
        'lng=${position.longitude} accuracy=${position.accuracy}',
      );
    }

    final accuracy = position.accuracy;
    if (accuracy == null || !accuracy.isFinite || accuracy <= 0) return;

    final previousAccuracy = _locationAccuracyMeters;
    final source = _sourceForLocationAccuracy(
      accuracy,
      emulator: position.isEmulator,
    );

    final previousLat = _latitude;
    final previousLng = _longitude;
    final moved = previousLat == null || previousLng == null
        ? double.infinity
        : _distanceMeters(
            previousLat,
            previousLng,
            position.latitude,
            position.longitude,
          );

    // V69 : ne plus figer le premier point uniquement parce qu'il annonçait une
    // accuracy légèrement meilleure. Un fix Fused plus récent peut corriger la
    // latitude/longitude de plusieurs dizaines de mètres avec une accuracy
    // comparable. On accepte cette correction tout en rejetant une vraie
    // dégradation grossière.
    if (previousAccuracy != null &&
        previousAccuracy.isFinite &&
        previousAccuracy > 0 &&
        _latitude != null &&
        _longitude != null) {
      final clearlyBetter = accuracy + 3 < previousAccuracy;
      final comparableAccuracy = accuracy <= (previousAccuracy * 1.25 + 5);
      final meaningfulCorrection =
          comparableAccuracy &&
          moved >= math.max(12.0, math.min(40.0, previousAccuracy * 0.5));
      if (!clearlyBetter && !meaningfulCorrection) {
        return;
      }
    }

    setState(() {
      _latitude = position.latitude;
      _longitude = position.longitude;
      _locationAccuracyMeters = accuracy;
      _geoSource = source;
      _savedAddressId = null;
      _geoStatus = '';
      _locationError = null;
    });

    // Au-delà de 500 m, on n'invente pas une adresse. Le stream continue et
    // remplacera ce point dès qu'Android fournit mieux.
    if (!position.isEmulator && accuracy > _addressLookupMaxAccuracyMeters) {
      return;
    }

    final now = DateTime.now();
    final sinceReverse = _lastReverseAt == null
        ? const Duration(days: 1)
        : now.difference(_lastReverseAt!);

    final improvedEnough =
        previousAccuracy == null ||
        !previousAccuracy.isFinite ||
        accuracy + 5 < previousAccuracy;

    if (!improvedEnough &&
        moved < 10 &&
        sinceReverse < const Duration(seconds: 6)) {
      return;
    }

    _lastTrackedLatitude = position.latitude;
    _lastTrackedLongitude = position.longitude;
    _lastReverseAt = now;

    final requestId = ++_liveReverseRequestId;
    try {
      final place = await _geoRepository.reverse(
        latitude: position.latitude,
        longitude: position.longitude,
        fresh: true,
      );
      if (!mounted ||
          requestId != _liveReverseRequestId ||
          (_geoSource != 'mobile_live_gps' &&
              _geoSource != 'mobile_location_assist')) {
        return;
      }

      _applyResolvedPlace(
        place,
        source: source,
        exactLatitude: position.latitude,
        exactLongitude: position.longitude,
      );
      if (mounted) {
        setState(() {
          _geoStatus = '';
          _locationError = null;
        });
      }
    } catch (_) {
      if (!mounted || requestId != _liveReverseRequestId) return;
      // Garder l'adresse déjà affichée ; une amélioration GPS ne doit jamais
      // effacer l'adresse si le reverse-géocodage secondaire échoue.
      setState(() {
        _geoStatus = '';
      });
    }
  }

  double _distanceMeters(double lat1, double lng1, double lat2, double lng2) {
    const earthRadius = 6371000.0;
    double radians(double value) => value * math.pi / 180.0;
    final dLat = radians(lat2 - lat1);
    final dLng = radians(lng2 - lng1);
    final a =
        math.sin(dLat / 2) * math.sin(dLat / 2) +
        math.cos(radians(lat1)) *
            math.cos(radians(lat2)) *
            math.sin(dLng / 2) *
            math.sin(dLng / 2);
    return 2 * earthRadius * math.atan2(math.sqrt(a), math.sqrt(1 - a));
  }

  bool _hasHumanReadableLocation(GeoResolvedPlace place) {
    return place.hasDeliveryLevelLocation;
  }

  String _formatAccuracy(double? accuracy) {
    if (accuracy == null || !accuracy.isFinite || accuracy <= 0) return '';
    if (accuracy >= 1000) {
      final km = accuracy / 1000;
      return '±${km >= 10 ? km.toStringAsFixed(0) : km.toStringAsFixed(1)} km';
    }
    return '±${accuracy.round()} m';
  }

  String _gpsStatusForAccuracy(double? accuracy, {bool emulator = false}) {
    if (emulator) return 'Position simulée de l’émulateur — test uniquement.';
    return '';
  }

  void _applyResolvedPlace(
    GeoResolvedPlace place, {
    required String source,
    double? exactLatitude,
    double? exactLongitude,
  }) {
    setState(() {
      _zone = place.zone;
      _commune = place.commune;
      _quartier = place.quartier;
      _city = place.city;
      _address = place.displayName;
      _search.text = place.displayName;
      // IMPORTANT V43 : le géocodeur sert à nommer le lieu, jamais à déplacer
      // le point GPS vers le centre d’un quartier/POI.
      _latitude = exactLatitude ?? place.latitude;
      _longitude = exactLongitude ?? place.longitude;
      _savedAddressId = null;
      _geoSource = source;
      _localityType = place.localityType;
      if (source != 'mobile_live_gps' && source != 'mobile_location_assist') {
        _locationAccuracyMeters = null;
      }
      _locationError = null;
      _searchResults = const [];
    });
  }

  void _editSelectedLocation() {
    _stopLiveTracking();
    setState(() {
      _address = '';
      _commune = '';
      _quartier = '';
      _city = '';
      _latitude = null;
      _longitude = null;
      _savedAddressId = null;
      _geoSource = 'mobile_manual';
      _localityType = '';
      _locationAccuracyMeters = null;
      _locationError = null;
    });
  }

  void _submit() {
    final formOk = _formKey.currentState?.validate() ?? false;
    if (_geoSource == 'mobile_live_gps') {
      final accuracy = _locationAccuracyMeters;
      if (accuracy != null &&
          accuracy.isFinite &&
          accuracy > DeviceLocation.maxAcceptedAccuracyMeters) {
        // Si Android n'a pas un fix fin mais qu'une adresse a bien été
        // identifiée, reclasser la source en aide de localisation plutôt que
        // bloquer le checkout sur une métrique GPS invisible pour le client.
        _geoSource = 'mobile_location_assist';
      }
    }
    if (!_hasSelectedLocation) {
      final gpsAddressVisible =
          (_geoSource == 'mobile_live_gps' ||
              _geoSource == 'mobile_location_assist') &&
          _address.trim().isNotEmpty;
      setState(() {
        _locationError = gpsAddressVisible
            ? 'Impossible de confirmer cette position. Relancez « Ma position ».'
            : 'Choisissez une adresse proposée ou utilisez « Ma position ».';
      });
      return;
    }
    if (!formOk) return;

    _stopLiveTracking();
    Navigator.of(context).pop(
      _AddressFormResult(
        name: _name.text.trim(),
        phone: _phone.text.trim(),
        zone: _zone,
        commune: _commune,
        quartier: _quartier,
        city: _city,
        address: _address,
        notes: _notes,
        latitude: _latitude,
        longitude: _longitude,
        geoAccuracyMeters: _locationAccuracyMeters,
        savedAddressId: _savedAddressId,
        geoSource: _geoSource,
        localityType: _localityType,
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final keyboard = MediaQuery.viewInsetsOf(context).bottom;

    return FractionallySizedBox(
      heightFactor: 0.94,
      child: Container(
        decoration: const BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
        ),
        child: Column(
          children: [
            Padding(
              padding: const EdgeInsets.fromLTRB(18, 14, 12, 10),
              child: Row(
                children: [
                  const Expanded(
                    child: Text(
                      'Adresse de livraison',
                      style: TextStyle(
                        color: OvanieColors.navy,
                        fontSize: 20,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                  ),
                  IconButton(
                    tooltip: 'Fermer',
                    onPressed: () => Navigator.of(context).pop(),
                    icon: const Icon(Icons.close_rounded),
                  ),
                ],
              ),
            ),
            const Divider(height: 1),
            Expanded(
              child: Form(
                key: _formKey,
                child: ListView(
                  padding: EdgeInsets.fromLTRB(18, 16, 18, 18 + keyboard),
                  children: [
                    if (widget.loadingSavedAddresses)
                      const LinearProgressIndicator(minHeight: 2)
                    else if (widget.savedAddresses.isNotEmpty) ...[
                      _AddressSectionCard(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            const _SheetSectionTitle(
                              number: '✓',
                              title: 'Mes adresses enregistrées',
                              subtitle:
                                  'Choisissez une adresse de votre carnet ou recherchez une nouvelle adresse.',
                            ),
                            const SizedBox(height: 12),
                            DropdownButtonFormField<int>(
                              value:
                                  widget.savedAddresses.any(
                                    (item) => item.id == _savedAddressId,
                                  )
                                  ? _savedAddressId
                                  : null,
                              isExpanded: true,
                              decoration: const InputDecoration(
                                labelText: 'Adresse enregistrée',
                              ),
                              items: widget.savedAddresses
                                  .map(
                                    (item) => DropdownMenuItem<int>(
                                      value: item.id,
                                      child: Text(
                                        '${item.label} — ${item.commune.isNotEmpty ? item.commune : item.city}',
                                        maxLines: 1,
                                        overflow: TextOverflow.ellipsis,
                                      ),
                                    ),
                                  )
                                  .toList(growable: false),
                              onChanged: (id) {
                                if (id != null) _applySavedAddress(id);
                              },
                            ),
                          ],
                        ),
                      ),
                      const SizedBox(height: 14),
                    ],
                    _AddressSectionCard(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          const _SheetSectionTitle(
                            number: '1',
                            title: 'Lieu de livraison',
                            subtitle:
                                'Recherchez un quartier, une rue, une résidence ou un lieu connu.',
                          ),
                          const SizedBox(height: 14),
                          const Text(
                            'Adresse ou lieu',
                            style: TextStyle(
                              color: OvanieColors.text,
                              fontSize: 12,
                              fontWeight: FontWeight.w900,
                            ),
                          ),
                          const SizedBox(height: 8),
                          Row(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Expanded(
                                child: TextField(
                                  controller: _search,
                                  onChanged: _onSearchChanged,
                                  textInputAction: TextInputAction.search,
                                  decoration: const InputDecoration(
                                    hintText:
                                        'Ex. Riviera 3, Angré, Zone 4, Bouaké...',
                                    prefixIcon: Icon(
                                      Icons.location_on_outlined,
                                      color: OvanieColors.orange,
                                    ),
                                  ),
                                ),
                              ),
                              const SizedBox(width: 8),
                              SizedBox(
                                width: 112,
                                height: 56,
                                child: FilledButton(
                                  onPressed: _locating
                                      ? null
                                      : _useCurrentPosition,
                                  style: FilledButton.styleFrom(
                                    padding: const EdgeInsets.symmetric(
                                      horizontal: 8,
                                    ),
                                    backgroundColor: OvanieColors.blue,
                                    foregroundColor: Colors.white,
                                    shape: RoundedRectangleBorder(
                                      borderRadius: BorderRadius.circular(12),
                                    ),
                                  ),
                                  child: _locating
                                      ? const SizedBox(
                                          width: 18,
                                          height: 18,
                                          child: CircularProgressIndicator(
                                            strokeWidth: 2,
                                            color: Colors.white,
                                          ),
                                        )
                                      : const Row(
                                          mainAxisAlignment:
                                              MainAxisAlignment.center,
                                          children: [
                                            Icon(
                                              Icons.my_location_rounded,
                                              size: 16,
                                            ),
                                            SizedBox(width: 5),
                                            Flexible(
                                              child: Text(
                                                'Ma position',
                                                maxLines: 1,
                                                style: TextStyle(
                                                  fontSize: 10.5,
                                                  fontWeight: FontWeight.w900,
                                                ),
                                              ),
                                            ),
                                          ],
                                        ),
                                ),
                              ),
                            ],
                          ),
                          if (_searching || _geoStatus.trim().isNotEmpty) ...[
                            const SizedBox(height: 8),
                            Row(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                if (_searching) ...[
                                  const SizedBox(
                                    width: 13,
                                    height: 13,
                                    child: CircularProgressIndicator(
                                      strokeWidth: 1.8,
                                    ),
                                  ),
                                  const SizedBox(width: 7),
                                ],
                                Expanded(
                                  child: Text(
                                    _searching
                                        ? 'Recherche de l’adresse…'
                                        : _geoStatus,
                                    style: const TextStyle(
                                      color: OvanieColors.muted,
                                      fontSize: 9.8,
                                      height: 1.35,
                                    ),
                                  ),
                                ),
                              ],
                            ),
                          ],
                          if (_locationError != null) ...[
                            const SizedBox(height: 10),
                            _InlineAddressError(message: _locationError!),
                          ],
                          if (_searchResults.isNotEmpty) ...[
                            const SizedBox(height: 10),
                            Container(
                              decoration: BoxDecoration(
                                color: Colors.white,
                                borderRadius: BorderRadius.circular(12),
                                border: Border.all(color: OvanieColors.border),
                              ),
                              child: Column(
                                children: [
                                  for (
                                    var index = 0;
                                    index < _searchResults.length;
                                    index++
                                  ) ...[
                                    _AddressResultTile(
                                      result: _searchResults[index],
                                      onTap: () => _selectSearchResult(
                                        _searchResults[index],
                                      ),
                                    ),
                                    if (index != _searchResults.length - 1)
                                      const Divider(height: 1),
                                  ],
                                ],
                              ),
                            ),
                          ],
                          if (_hasSelectedLocation) ...[
                            const SizedBox(height: 12),
                            _SelectedLocationCard(
                              city: _zone == 'abidjan'
                                  ? 'Abidjan'
                                  : (_city.isEmpty ? _commune : _city),
                              commune: _commune,
                              quartier: _quartier,
                              address: _address,
                              onEdit: _editSelectedLocation,
                            ),
                          ],
                        ],
                      ),
                    ),
                    const SizedBox(height: 14),
                    _AddressSectionCard(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          const _SheetSectionTitle(
                            number: '2',
                            title: 'Contact de livraison',
                            subtitle:
                                'Ces coordonnées servent au suivi et à la remise de la commande.',
                          ),
                          const SizedBox(height: 14),
                          TextFormField(
                            controller: _name,
                            textInputAction: TextInputAction.next,
                            decoration: const InputDecoration(
                              labelText: 'Nom complet',
                            ),
                            validator: (value) =>
                                _required(value, 'Renseignez le nom complet.'),
                          ),
                          const SizedBox(height: 11),
                          TextFormField(
                            controller: _phone,
                            keyboardType: TextInputType.phone,
                            inputFormatters: const [
                              CiPhoneInputFormatter(withCountryCode: true),
                            ],
                            decoration: const InputDecoration(
                              labelText: 'Numéro WhatsApp',
                              hintText: '+225 07 01 00 00 00',
                            ),
                            validator: (value) => _required(
                              value,
                              'Renseignez le numéro WhatsApp.',
                            ),
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: 18),
                  ],
                ),
              ),
            ),
            Container(
              padding: EdgeInsets.fromLTRB(
                18,
                12,
                18,
                12 + MediaQuery.paddingOf(context).bottom,
              ),
              decoration: const BoxDecoration(
                color: Colors.white,
                border: Border(top: BorderSide(color: OvanieColors.border)),
              ),
              child: Row(
                children: [
                  Expanded(
                    child: OutlinedButton(
                      onPressed: () => Navigator.of(context).pop(),
                      style: OutlinedButton.styleFrom(
                        minimumSize: const Size.fromHeight(48),
                        foregroundColor: OvanieColors.navy,
                      ),
                      child: const Text('Annuler'),
                    ),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    flex: 2,
                    child: FilledButton(
                      onPressed: _submit,
                      style: FilledButton.styleFrom(
                        minimumSize: const Size.fromHeight(48),
                        backgroundColor: OvanieColors.orange,
                        foregroundColor: Colors.white,
                      ),
                      child: const Text(
                        'Confirmer cette adresse',
                        style: TextStyle(fontWeight: FontWeight.w900),
                      ),
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
}

class _AddressSectionCard extends StatelessWidget {
  final Widget child;

  const _AddressSectionCard({required this.child});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: const Color(0xFFFBFCFE),
        borderRadius: BorderRadius.circular(15),
        border: Border.all(color: OvanieColors.border),
      ),
      child: child,
    );
  }
}

class _SheetSectionTitle extends StatelessWidget {
  final String number;
  final String title;
  final String subtitle;

  const _SheetSectionTitle({
    required this.number,
    required this.title,
    required this.subtitle,
  });

  @override
  Widget build(BuildContext context) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Container(
          width: 30,
          height: 30,
          alignment: Alignment.center,
          decoration: const BoxDecoration(
            color: OvanieColors.navy,
            shape: BoxShape.circle,
          ),
          child: Text(
            number,
            style: const TextStyle(
              color: Colors.white,
              fontSize: 12,
              fontWeight: FontWeight.w900,
            ),
          ),
        ),
        const SizedBox(width: 10),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                title,
                style: const TextStyle(
                  color: OvanieColors.navy,
                  fontSize: 13.5,
                  fontWeight: FontWeight.w900,
                ),
              ),
              const SizedBox(height: 2),
              Text(
                subtitle,
                style: const TextStyle(
                  color: OvanieColors.muted,
                  fontSize: 10.2,
                  height: 1.35,
                ),
              ),
            ],
          ),
        ),
      ],
    );
  }
}

class _AddressResultTile extends StatelessWidget {
  final GeoSearchResult result;
  final VoidCallback onTap;

  const _AddressResultTile({required this.result, required this.onTap});

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 11, vertical: 10),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Icon(
              Icons.location_on_outlined,
              size: 18,
              color: OvanieColors.orange,
            ),
            const SizedBox(width: 8),
            Expanded(
              child: Text(
                result.displayName,
                style: const TextStyle(
                  color: OvanieColors.text,
                  fontSize: 10.8,
                  height: 1.35,
                  fontWeight: FontWeight.w700,
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _SelectedLocationCard extends StatelessWidget {
  final String city;
  final String commune;
  final String quartier;
  final String address;
  final VoidCallback onEdit;

  const _SelectedLocationCard({
    required this.city,
    required this.commune,
    required this.quartier,
    required this.address,
    required this.onEdit,
  });

  String _shortLabel() {
    String clean(String value) => value.trim();

    final q = cleanOvanieText(clean(quartier));
    if (q.isNotEmpty) return q;

    final full = cleanOvanieText(clean(address));
    if (full.isNotEmpty) {
      final first = full.split(',').first.trim();
      if (first.isNotEmpty &&
          first.toLowerCase() != clean(city).toLowerCase() &&
          first.toLowerCase() != clean(commune).toLowerCase()) {
        return first;
      }
    }

    final c = cleanOvanieText(clean(commune));
    if (c.isNotEmpty && c.toLowerCase() != 'abidjan') return c;

    final v = cleanOvanieText(clean(city));
    return v.isNotEmpty ? v : 'Position actuelle';
  }

  @override
  Widget build(BuildContext context) {
    final cleanAddress = cleanOvanieText(address);
    final label = _shortLabel();

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 11),
      decoration: BoxDecoration(
        color: const Color(0xFFF1F8FF),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: const Color(0xFFB9D9FF)),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.center,
        children: [
          Container(
            width: 28,
            height: 28,
            alignment: Alignment.center,
            decoration: const BoxDecoration(
              color: OvanieColors.navy,
              shape: BoxShape.circle,
            ),
            child: const Icon(
              Icons.check_rounded,
              color: Colors.white,
              size: 18,
            ),
          ),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Adresse détectée · $label',
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    color: OvanieColors.text,
                    fontSize: 11.5,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                if (cleanAddress.isNotEmpty) ...[
                  const SizedBox(height: 3),
                  Text(
                    cleanAddress,
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      color: OvanieColors.muted,
                      fontSize: 10.2,
                      height: 1.3,
                    ),
                  ),
                ],
              ],
            ),
          ),
          const SizedBox(width: 6),
          TextButton(onPressed: onEdit, child: const Text('Modifier')),
        ],
      ),
    );
  }
}

class _LocationInfoLine extends StatelessWidget {
  final String label;
  final String value;

  const _LocationInfoLine({required this.label, required this.value});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 4),
      child: RichText(
        text: TextSpan(
          style: const TextStyle(
            color: OvanieColors.muted,
            fontSize: 9.8,
            height: 1.35,
          ),
          children: [
            TextSpan(
              text: '$label : ',
              style: const TextStyle(
                color: OvanieColors.text,
                fontWeight: FontWeight.w800,
              ),
            ),
            TextSpan(text: value),
          ],
        ),
      ),
    );
  }
}

class _InlineAddressError extends StatelessWidget {
  final String message;

  const _InlineAddressError({required this.message});

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(10),
      decoration: BoxDecoration(
        color: const Color(0xFFFFF1F2),
        borderRadius: BorderRadius.circular(10),
        border: Border.all(color: const Color(0xFFFECACA)),
      ),
      child: Text(
        message,
        style: const TextStyle(
          color: Color(0xFFB42318),
          fontSize: 10,
          height: 1.35,
          fontWeight: FontWeight.w700,
        ),
      ),
    );
  }
}

class _ErrorBox extends StatelessWidget {
  final String message;

  const _ErrorBox({required this.message});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: const Color(0xFFFFF1F2),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: const Color(0xFFFECACA)),
      ),
      child: Text(
        message,
        textAlign: TextAlign.center,
        style: const TextStyle(
          color: Color(0xFFB42318),
          fontSize: 11.5,
          height: 1.35,
          fontWeight: FontWeight.w700,
        ),
      ),
    );
  }
}

class _MobileOnlinePaymentScreen extends StatefulWidget {
  final CheckoutOrderResult order;
  final String initialPhone;
  final List<CheckoutPaymentOperatorOption> operators;

  const _MobileOnlinePaymentScreen({
    required this.order,
    required this.initialPhone,
    this.operators = const [],
  });

  @override
  State<_MobileOnlinePaymentScreen> createState() =>
      _MobileOnlinePaymentScreenState();
}

class _MobileOnlinePaymentScreenState extends State<_MobileOnlinePaymentScreen>
    with WidgetsBindingObserver {
  final _repository = const CheckoutRepository();
  late final TextEditingController _phone;
  late final TextEditingController _orangeOtp;
  String _operator = '';
  bool _submitting = false;
  String? _error;
  Timer? _paymentStatusTimer;
  bool _paymentOpened = false;
  bool _checkingPayment = false;
  bool _paymentCompleted = false;
  int _paymentPollCount = 0;

  static const _operatorVisuals =
      <String, ({String asset, String placeholder})>{
        'wave': (
          asset: 'assets/images/operators/wave.png',
          placeholder: '07 00 00 00 00',
        ),
        'orange': (
          asset: 'assets/images/operators/orange.png',
          placeholder: '07 00 00 00 00',
        ),
        'mtn': (
          asset: 'assets/images/operators/mtn.png',
          placeholder: '05 00 00 00 00',
        ),
        'moov': (
          asset: 'assets/images/operators/moov.png',
          placeholder: '01 00 00 00 00',
        ),
      };

  List<({String code, String label, String asset, String placeholder})>
  get _mobileOperators {
    final source = widget.operators
        .where(
          (item) =>
              item.code != 'card' && _operatorVisuals.containsKey(item.code),
        )
        .map((item) {
          final visual = _operatorVisuals[item.code]!;
          return (
            code: item.code,
            label: item.label,
            asset: visual.asset,
            placeholder: visual.placeholder,
          );
        })
        .toList(growable: false);

    return source;
  }

  bool get _cardEnabled =>
      widget.operators.any((item) => item.code == 'card');

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _phone = TextEditingController(
      text: formatCiPhoneDisplay(
        widget.initialPhone,
        includeCountryCode: false,
      ),
    );
    _orangeOtp = TextEditingController();
    _operator = _mobileOperators.isNotEmpty ? _mobileOperators.first.code : '';
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    _paymentStatusTimer?.cancel();
    _phone.dispose();
    _orangeOtp.dispose();
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed &&
        _paymentOpened &&
        !_paymentCompleted) {
      unawaited(_checkPaymentStatus());
    }
  }

  ({String code, String label, String asset, String placeholder})?
  get _selected {
    if (_mobileOperators.isEmpty) return null;
    return _mobileOperators.firstWhere(
      (item) => item.code == _operator,
      orElse: () => _mobileOperators.first,
    );
  }

  Future<void> _pay({String? forceOperator}) async {
    if (_submitting) return;
    final operator = forceOperator ?? _operator;
    if (operator.isEmpty) {
      setState(() {
        _error =
            'Les opérateurs Mobile Money OVANIE sont indisponibles. Actualisez puis réessayez.';
      });
      return;
    }
    final isCard = operator == 'card';
    final localPhone = ciLocalPhoneDigits(_phone.text);
    final phone = localPhone.length == 10
        ? '+225$localPhone'
        : _phone.text.trim();

    if (!isCard && localPhone.length != 10) {
      setState(() => _error = 'Renseignez le numéro Mobile Money à débiter.');
      return;
    }

    setState(() {
      _submitting = true;
      _error = null;
    });

    try {
      final result = await _repository.startOnlinePayment(
        orderId: widget.order.orderId,
        operator: operator,
        phone: isCard ? '' : phone,
        orangeOtp: operator == 'orange' ? _orangeOtp.text.trim() : '',
      );
      if (!mounted) return;

      if (result.paymentUrl.trim().isNotEmpty) {
        await InAppPaymentScreen.open(context, result.paymentUrl);
        if (!mounted) return;
      }
      _paymentOpened = true;
      _startPaymentPolling();
    } catch (error) {
      if (!mounted) return;
      setState(() => _error = ApiClient.friendlyError(error));
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  void _startPaymentPolling() {
    _paymentStatusTimer?.cancel();
    _paymentPollCount = 0;
    _paymentStatusTimer = Timer.periodic(const Duration(seconds: 4), (timer) {
      if (!mounted || _paymentCompleted || _paymentPollCount >= 30) {
        timer.cancel();
        return;
      }
      _paymentPollCount++;
      unawaited(_checkPaymentStatus());
    });
    unawaited(_checkPaymentStatus());
  }

  Future<void> _checkPaymentStatus() async {
    if (_checkingPayment || _paymentCompleted) return;
    _checkingPayment = true;
    try {
      final order = await const OrdersRepository().fetchOrder(
        widget.order.orderId,
      );
      if (!order.isPaymentConfirmed) return;

      _paymentCompleted = true;
      _paymentStatusTimer?.cancel();

      // Le webhook Laravel a déjà vidé le panier canonique. On supprime
      // immédiatement le miroir local puis on confirme l'état serveur pour que
      // le badge Panier soit mis à jour sans redémarrer l'application.
      await CartStore.instance.clearLocalMirrorOnly();
      try {
        await const CartApiRepository().refreshLocalCartFromServer();
      } catch (_) {
        // Le webhook/retour Laravel est la source de vérité. Le miroir local
        // est déjà vide et sera resynchronisé au prochain accès réseau.
      }

      if (!mounted) return;
      await Navigator.of(context).pushReplacement(
        MaterialPageRoute<void>(
          builder: (_) => OrderConfirmationScreen(
            orderId: order.id,
            initialOrderNumber: order.orderNumber,
            initialAmount: order.total,
            initialPaymentMethod: 'Paiement en ligne',
            returnState: 'completed',
          ),
        ),
      );
    } catch (_) {
      // Le paiement peut être encore en propagation chez PayDunya. Garder
      // l'écran actif et laisser la prochaine vérification réessayer.
    } finally {
      _checkingPayment = false;
    }
  }

  @override
  Widget build(BuildContext context) {
    final amount = widget.order.paymentAmount;
    final selected = _selected;

    return Scaffold(
      backgroundColor: OvanieColors.background,
      appBar: AppBar(
        leading: IconButton(
          tooltip: 'Retour',
          onPressed: () => Navigator.of(context).maybePop(),
          icon: const Icon(Icons.arrow_back_rounded),
        ),
        title: const Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(Icons.lock_outline_rounded, size: 20),
            SizedBox(width: 8),
            Text(
              'Paiement sécurisé',
              style: TextStyle(fontWeight: FontWeight.w900),
            ),
          ],
        ),
      ),
      body: SafeArea(
        child: ListView(
          padding: const EdgeInsets.fromLTRB(16, 22, 16, 28),
          children: [
            Container(
              padding: const EdgeInsets.fromLTRB(18, 22, 18, 20),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(20),
                border: Border.all(color: OvanieColors.border),
                boxShadow: const [
                  BoxShadow(
                    color: Color(0x0D071B48),
                    blurRadius: 20,
                    offset: Offset(0, 8),
                  ),
                ],
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Text(
                    'Paiement Mobile Money',
                    textAlign: TextAlign.center,
                    style: TextStyle(
                      color: OvanieColors.navy,
                      fontSize: 24,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                  const SizedBox(height: 7),
                  const Text(
                    'Choisissez votre opérateur et saisissez le numéro à débiter.',
                    textAlign: TextAlign.center,
                    style: TextStyle(
                      color: OvanieColors.muted,
                      fontSize: 12.5,
                      height: 1.4,
                    ),
                  ),
                  const SizedBox(height: 20),
                  Container(
                    padding: const EdgeInsets.symmetric(
                      horizontal: 14,
                      vertical: 14,
                    ),
                    decoration: BoxDecoration(
                      color: const Color(0xFFF8FAFD),
                      borderRadius: BorderRadius.circular(14),
                      border: Border.all(color: OvanieColors.border),
                    ),
                    child: Row(
                      children: [
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              const Text(
                                'Commande',
                                style: TextStyle(
                                  color: OvanieColors.muted,
                                  fontSize: 11.5,
                                  fontWeight: FontWeight.w700,
                                ),
                              ),
                              const SizedBox(height: 3),
                              Text(
                                widget.order.orderNumber,
                                style: const TextStyle(
                                  color: OvanieColors.navy,
                                  fontSize: 13.5,
                                  fontWeight: FontWeight.w900,
                                ),
                              ),
                            ],
                          ),
                        ),
                        Column(
                          crossAxisAlignment: CrossAxisAlignment.end,
                          children: [
                            const Text(
                              'Montant',
                              style: TextStyle(
                                color: OvanieColors.muted,
                                fontSize: 11.5,
                                fontWeight: FontWeight.w700,
                              ),
                            ),
                            const SizedBox(height: 3),
                            Text(
                              formatFcfa(amount),
                              style: const TextStyle(
                                color: OvanieColors.orange,
                                fontSize: 17,
                                fontWeight: FontWeight.w900,
                              ),
                            ),
                          ],
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 20),
                  const Text(
                    'Opérateur',
                    style: TextStyle(
                      color: OvanieColors.text,
                      fontSize: 12.5,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                  const SizedBox(height: 7),
                  DropdownButtonFormField<String>(
                    value: _operator.isEmpty ? null : _operator,
                    isExpanded: true,
                    decoration: const InputDecoration(
                      contentPadding: EdgeInsets.symmetric(
                        horizontal: 12,
                        vertical: 14,
                      ),
                    ),
                    selectedItemBuilder: (context) => _mobileOperators
                        .map(
                          (item) => Row(
                            children: [
                              SizedBox(
                                width: 34,
                                height: 28,
                                child: Image.asset(
                                  item.asset,
                                  fit: BoxFit.contain,
                                ),
                              ),
                              const SizedBox(width: 10),
                              Text(
                                item.label,
                                style: const TextStyle(
                                  color: OvanieColors.text,
                                  fontWeight: FontWeight.w900,
                                ),
                              ),
                            ],
                          ),
                        )
                        .toList(growable: false),
                    items: _mobileOperators
                        .map(
                          (item) => DropdownMenuItem<String>(
                            value: item.code,
                            child: Row(
                              children: [
                                SizedBox(
                                  width: 34,
                                  height: 28,
                                  child: Image.asset(
                                    item.asset,
                                    fit: BoxFit.contain,
                                  ),
                                ),
                                const SizedBox(width: 10),
                                Text(item.label),
                              ],
                            ),
                          ),
                        )
                        .toList(growable: false),
                    onChanged: _submitting
                        ? null
                        : (value) => setState(() {
                            _operator = value ?? '';
                            _error = null;
                          }),
                  ),
                  const SizedBox(height: 16),
                  const Text(
                    'Numéro Mobile Money',
                    style: TextStyle(
                      color: OvanieColors.text,
                      fontSize: 12.5,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                  const SizedBox(height: 7),
                  TextField(
                    controller: _phone,
                    enabled: !_submitting,
                    keyboardType: TextInputType.phone,
                    inputFormatters: const [CiPhoneInputFormatter()],
                    decoration: InputDecoration(
                      prefixText: '+225   ',
                      hintText: selected?.placeholder ?? '07 00 00 00 00',
                    ),
                  ),
                  if (_operator == 'orange') ...[
                    const SizedBox(height: 13),
                    TextField(
                      controller: _orangeOtp,
                      enabled: !_submitting,
                      keyboardType: TextInputType.number,
                      decoration: const InputDecoration(
                        labelText: 'OTP Orange Money (si demandé)',
                        hintText: 'Code à usage unique',
                      ),
                    ),
                  ],
                  if (_error != null) ...[
                    const SizedBox(height: 14),
                    _ErrorBox(message: _error!),
                  ],
                  const SizedBox(height: 18),
                  SizedBox(
                    height: 54,
                    child: FilledButton(
                      onPressed: _submitting || _operator.isEmpty
                          ? null
                          : () => _pay(),
                      style: FilledButton.styleFrom(
                        backgroundColor: OvanieColors.orange,
                        foregroundColor: Colors.white,
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(13),
                        ),
                      ),
                      child: _submitting
                          ? const SizedBox(
                              width: 22,
                              height: 22,
                              child: CircularProgressIndicator(
                                strokeWidth: 2.3,
                                color: Colors.white,
                              ),
                            )
                          : Row(
                              children: [
                                const Text(
                                  'Payer maintenant',
                                  style: TextStyle(fontWeight: FontWeight.w900),
                                ),
                                const Spacer(),
                                Text(
                                  formatFcfa(amount),
                                  style: const TextStyle(
                                    fontWeight: FontWeight.w900,
                                  ),
                                ),
                              ],
                            ),
                    ),
                  ),
                  const SizedBox(height: 13),
                  const Row(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Icon(
                        Icons.verified_user_outlined,
                        size: 17,
                        color: Color(0xFF159455),
                      ),
                      SizedBox(width: 7),
                      Flexible(
                        child: Text(
                          'Transaction sécurisée. Ne communiquez jamais votre code secret.',
                          textAlign: TextAlign.center,
                          style: TextStyle(
                            color: OvanieColors.muted,
                            fontSize: 10.5,
                          ),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 18),
                  const Divider(),
                  const SizedBox(height: 10),
                  const Text(
                    'Moyens acceptés',
                    textAlign: TextAlign.center,
                    style: TextStyle(
                      color: OvanieColors.muted,
                      fontSize: 11,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                  const SizedBox(height: 10),
                  Row(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: _mobileOperators
                        .map(
                          (item) => Padding(
                            padding: const EdgeInsets.symmetric(horizontal: 7),
                            child: SizedBox(
                              width: 38,
                              height: 28,
                              child: Image.asset(
                                item.asset,
                                fit: BoxFit.contain,
                              ),
                            ),
                          ),
                        )
                        .toList(growable: false),
                  ),
                  if (_cardEnabled) ...[
                    const SizedBox(height: 16),
                    OutlinedButton.icon(
                      onPressed: _submitting
                          ? null
                          : () => _pay(forceOperator: 'card'),
                      icon: const Icon(Icons.credit_card_rounded),
                      label: const Text('Payer par carte bancaire'),
                    ),
                  ],
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

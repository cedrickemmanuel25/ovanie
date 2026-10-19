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
import '../../auth/domain/session_store.dart';
import '../../cart/data/cart_api_repository.dart';
import '../../cart/domain/cart_store.dart';
import '../../orders/data/orders_repository.dart';
import '../data/abidjan_localities.dart';
import '../data/checkout_repository.dart';
import '../data/geo_repository.dart';
import '../domain/checkout_models.dart';
import '../../payments/presentation/payment_ui.dart';
import '../../payments/presentation/payment_waiting_screen.dart';
import 'order_confirmation_screen.dart';

class CheckoutScreen extends StatefulWidget {
  final List<int> selectedProductIds;

  const CheckoutScreen({super.key, this.selectedProductIds = const <int>[]});

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
  // Numéro du dernier calcul demandé : seul le plus récent (adresse
  // actuellement choisie) a le droit de mettre à jour l'écran.
  int _previewRequest = 0;
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
  // 'default' = adresse par défaut, 'new' = nouvelle adresse. Tant que le
  // client n'a pas choisi, aucun frais de livraison n'est calculé.
  String? _addressChoice;
  int _stage = 1;
  bool _customCommune = false;
  bool _customQuartier = false;
  bool _summaryExpanded = false;
  bool _paymentSubmitted = false;
  // Avertissement « position trop imprécise » affiché sous l'adresse.
  String? _locationWarning;

  Set<int> get _selectedProductIds => widget.selectedProductIds.where((id) => id > 0).toSet();

  Iterable<CartLine> get _selectedCartLines {
    final selected = _selectedProductIds;
    if (selected.isEmpty) return CartStore.instance.lines;
    return CartStore.instance.lines.where((line) => selected.contains(line.product.id));
  }

  double get _selectedSubtotal => _selectedCartLines.fold<double>(0, (sum, line) => sum + line.subtotal);

  List<CheckoutPaymentOperatorOption> get _availableOnlineOperators {
    final apiOperators = _onlinePaymentOption?.operators ?? const <CheckoutPaymentOperatorOption>[];
    if (apiOperators.isNotEmpty) return apiOperators;

    final references = OvanieReferenceDataStore.instance.options('checkout_operators');
    if (references.isNotEmpty) {
      return references
          .map((item) => CheckoutPaymentOperatorOption(code: item.code, label: item.label))
          .toList(growable: false);
    }

    return const <CheckoutPaymentOperatorOption>[];
  }

  @override
  void initState() {
    super.initState();
    _name = TextEditingController(text: SessionStore.instance.name ?? '');
    _phone = TextEditingController(text: formatCiPhoneDisplay(SessionStore.instance.phone ?? ''));
    _communeController = TextEditingController();
    _quartierController = TextEditingController();
    _notesController = TextEditingController();
    _addressController = TextEditingController();
    final sync = const CartApiRepository().mergeLocalCartIntoServer(refreshLocal: false);
    _cartSyncSettled = sync.then<void>(
      (_) {},
      onError: (Object error, StackTrace stackTrace) {
        _cartSyncError = error;
      },
    );
    unawaited(_loadSavedAddresses());
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

  Future<void> _loadSavedAddresses() async {
    try {
      final addresses = await _repository.savedAddresses();
      if (!mounted) return;
      setState(() {
        _savedAddresses = addresses;
        _loadingAddresses = false;
        // Aucune adresse enregistrée : il n'y a rien à choisir.
        if (addresses.isEmpty && _addressChoice == null) _addressChoice = 'new';
      });
      // La maquette présente directement l'adresse par défaut du client ;
      // il peut la changer avec « Changer ».
      final initial = _defaultAddress;
      if (initial != null && !_addressConfirmed && _savedAddressId == null) {
        _addressChoice = 'default';
        _applySavedAddress(initial);
      }
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _loadingAddresses = false;
        _addressChoice ??= 'new';
      });
    }
  }

  /// Adresse proposée en premier : celle marquée par défaut, sinon la plus
  /// récente des adresses enregistrées.
  CheckoutSavedAddress? get _defaultAddress {
    for (final address in _savedAddresses) {
      if (address.isDefault) return address;
    }
    return _savedAddresses.isEmpty ? null : _savedAddresses.first;
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
    _name.text = item.recipientName.trim().isNotEmpty ? item.recipientName.trim() : _name.text;
    _phone.text = formatCiPhoneDisplay(item.phone.trim().isNotEmpty ? item.phone : _phone.text);
    final isAbidjan = item.city.toLowerCase().contains('abidjan') || item.commune.trim().isNotEmpty;
    setState(() {
      _savedAddressId = item.id;
      _zone = isAbidjan ? 'abidjan' : 'interieur';
      _city = item.city;
      _commune = item.commune;
      _quartier = item.sousQuartier.trim().isNotEmpty ? item.sousQuartier : item.quartier;
      _address = item.address;
      _latitude = item.latitude;
      _longitude = item.longitude;
      _localityType = item.localityType;
      _customCommune = false;
      _customQuartier = false;
      _geoSource = item.latitude != null && item.longitude != null ? 'saved_address' : 'mobile_manual';
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
      _addressChoice = 'sheet';
      _locationWarning = null;
      _preview = null;
      _error = null;
    });
    _syncManualAddressControllers();
    unawaited(_refreshDeliveryPreview());
  }

  Future<void> _useCurrentPosition() async {
    if (_locating) return;
    FocusManager.instance.primaryFocus?.unfocus();
    setState(() {
      _locating = true;
      _error = null;
      _locationWarning = null;
    });
    try {
      var position = await DeviceLocation.currentPosition();

      // Sur un vrai téléphone, on laisse quelques secondes au fournisseur
      // Fused/GNSS pour améliorer la précision. Si Android ne descend pas sous
      // 30 m mais fournit tout de même un point réel exploitable, on conserve
      // ce meilleur point au lieu de bloquer complètement le checkout.
      if (!position.isEmulator && !position.hasGoodDeliveryAccuracy) {
        try {
          final precise = await DeviceLocation.positionStream(
            distanceFilterMeters: 0,
          ).firstWhere((candidate) => candidate.hasGoodDeliveryAccuracy).timeout(const Duration(seconds: 8));
          position = precise;
        } catch (_) {
          final accuracy = position.accuracy;
          if (accuracy == null || !accuracy.isFinite || accuracy <= 0 || accuracy > 250) {
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
    } on DeviceLocationException catch (error) {
      if (!mounted) return;
      if (isLocationPermissionProblem(error.message)) {
        setState(() => _error = null);
        final retry = await showLocationPermissionDialog(context, error);
        if (retry && mounted) {
          // Laisse le dialogue se fermer avant de relancer la demande Android.
          WidgetsBinding.instance.addPostFrameCallback((_) {
            if (mounted) unawaited(_useCurrentPosition());
          });
        }
      } else {
        setState(() => _locationWarning = error.message);
      }
    } catch (error) {
      if (!mounted) return;
      setState(() => _error = ApiClient.friendlyError(error));
    } finally {
      if (mounted) setState(() => _locating = false);
    }
  }

  Future<void> _refreshDeliveryPreview() async {
    if (!_addressConfirmed) return;
    // Un nouveau choix d'adresse pendant un calcul ne doit jamais être ignoré :
    // on relance, et la réponse de l'ancien calcul sera écartée.
    final request = ++_previewRequest;
    setState(() {
      _calculating = true;
      _error = null;
    });
    try {
      await _ensureCartReady();
      final payload = _payload();
      var preview = await _repository.preview(payload);
      if (request != _previewRequest) return;
      final serverSaysEmpty =
          preview.subtotal <= 0 && preview.deliveryMessage.toLowerCase().contains('panier est vide');
      if (serverSaysEmpty && CartStore.instance.lines.isNotEmpty) {
        await const CartApiRepository().mergeLocalCartIntoServer(refreshLocal: false);
        preview = await _repository.preview(payload);
        if (request != _previewRequest) return;
      }
      if (preview.subtotal <= 0 &&
          preview.deliveryMessage.toLowerCase().contains('panier est vide') &&
          CartStore.instance.lines.isNotEmpty) {
        throw const OvanieApiException('Votre panier est en cours de mise à jour. Réessayez dans quelques secondes.');
      }
      if (!mounted) return;
      setState(() {
        if (preview.deliveryZone.isNotEmpty) _zone = preview.deliveryZone;
        if (preview.deliveryCommune.isNotEmpty) _commune = preview.deliveryCommune;
        if (preview.deliveryQuartier.isNotEmpty) _quartier = preview.deliveryQuartier;
        if (preview.deliveryCity.isNotEmpty) _city = preview.deliveryCity;
        if (preview.deliveryAddress.isNotEmpty) _address = preview.deliveryAddress;
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
        } else if (_paymentMethod == 'paydunya' && online != null && !online.enabled) {
          _paymentMethod = '';
          _onlineOperator = '';
        } else if (_paymentMethod == 'cash_on_delivery' && cod != null && !cod.enabled) {
          _paymentMethod = '';
        }
        _preview = preview;
      });
      _syncManualAddressControllers();
    } catch (error) {
      if (!mounted || request != _previewRequest) return;
      setState(() {
        _preview = null;
        _error = ApiClient.friendlyError(error);
      });
    } finally {
      if (mounted && request == _previewRequest) {
        setState(() => _calculating = false);
      }
    }
  }

  bool get _deliveryReady {
    final preview = _preview;
    return preview != null && preview.deliveryCalculated && preview.deliveryAvailable && !preview.deliveryQuoteRequired;
  }

  CheckoutPaymentMethodOption? get _onlinePaymentOption => _preview?.paymentOptions.byCode('paydunya');
  CheckoutPaymentMethodOption? get _cashOnDeliveryOption => _preview?.paymentOptions.byCode('cash_on_delivery');
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
    FocusManager.instance.primaryFocus?.unfocus();
    if (!_addressConfirmed) {
      await _openAddressSheet();
      return;
    }
    if (!_deliveryReady) await _refreshDeliveryPreview();
    if (!mounted || !_deliveryReady) return;

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
        _error = 'Les moyens de paiement OVANIE sont momentanément indisponibles. Réessayez après actualisation.';
      });
      return;
    }
    final currentOperatorIsValid = _onlineOperator.isNotEmpty && operators.any((item) => item.code == _onlineOperator);

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
      _paymentPhone = operator.code == 'card' ? '' : normalizeCiPhoneForApi(_phone.text);
      _orangeOtp = '';
      _error = null;
    });
  }

  Future<void> _placeOrder() async {
    if (_placing) return;
    if (!SessionStore.instance.isAuthenticated) {
      setState(() => _error = 'Votre session a expiré. Reconnectez-vous avant de commander.');
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
        _error = 'Choisissez Wave, Orange Money, MTN MoMo, Moov Money ou carte bancaire.';
      });
      return;
    }
    if (_paymentMethod == 'paydunya' && _onlineOperator != 'card') {
      final paymentDigits = ciLocalPhoneDigits(_paymentPhone);
      if (paymentDigits.length != 10) {
        setState(() {
          _stage = 2;
          _error = 'Saisissez le numéro Mobile Money ivoirien à 10 chiffres qui sera débité.';
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
          builder: (_) => PaymentWaitingScreen(
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
        if (item.id == _savedAddressId && item.label.trim().isNotEmpty) return item.label.trim();
      }
    }
    if (_quartier.trim().isNotEmpty) return _quartier.trim();
    if (_commune.trim().isNotEmpty) return _commune.trim();
    if (_city.trim().isNotEmpty) return _city.trim();
    return 'Adresse de livraison';
  }

  void _goBack() {
    FocusManager.instance.primaryFocus?.unfocus();
    if (_stage > 1) {
      setState(() {
        _stage -= 1;
        _error = null;
        _paymentSubmitted = false;
      });
      return;
    }
    Navigator.of(context).maybePop();
  }

  double get _amountToPay => _preview?.total ?? _selectedSubtotal;

  CheckoutSavedAddress? get _currentSavedAddress {
    for (final item in _savedAddresses) {
      if (item.id == _savedAddressId) return item;
    }
    return null;
  }

  bool get _paymentMethodMissing => _paymentMethod.isEmpty || (_paymentMethod == 'paydunya' && _onlineOperator.isEmpty);

  bool get _paymentPhoneInvalid =>
      _paymentMethod == 'paydunya' &&
      _onlineOperator.isNotEmpty &&
      _onlineOperator != 'card' &&
      ciLocalPhoneDigits(_paymentPhone).length != 10;

  void _continueFromPayment() {
    FocusManager.instance.primaryFocus?.unfocus();
    setState(() {
      _paymentSubmitted = true;
      _error = null;
    });
    if (_paymentMethodMissing || _paymentPhoneInvalid) return;
    if (!_paymentSelectionReady) {
      final reason = _paymentMethod == 'cash_on_delivery'
          ? _cashOnDeliveryOption?.reason ?? ''
          : _onlinePaymentOption?.reason ?? '';
      setState(() {
        _error = reason.trim().isNotEmpty ? reason : 'Ce mode de paiement n’est pas disponible pour cette commande.';
      });
      return;
    }
    setState(() {
      _stage = 3;
      _paymentSubmitted = false;
    });
  }

  @override
  Widget build(BuildContext context) {
    final Widget body;
    final Widget bottomBar;
    final cashOnDelivery = _paymentMethod == 'cash_on_delivery';
    switch (_stage) {
      case 1:
        body = _buildDeliveryStage();
        bottomBar = _CheckoutTotalBar(
          amountLabel: _deliveryReady ? formatFcfa(_preview!.total) : 'À confirmer',
          buttonLabel: 'Vérifier la livraison',
          loading: false,
          onPressed: _continueFromAddress,
        );
      case 2:
        body = _buildPaymentStage();
        bottomBar = _CheckoutTotalBar(
          amountLabel: formatFcfa(_amountToPay),
          buttonLabel: cashOnDelivery ? 'Confirmer la commande' : 'Payer maintenant',
          loading: false,
          onPressed: _continueFromPayment,
        );
      default:
        body = _buildConfirmationStage();
        bottomBar = _CheckoutWideBar(
          label: cashOnDelivery ? 'Confirmer la commande' : 'Payer maintenant',
          loading: _placing,
          onPressed: _placeOrder,
        );
    }

    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: SystemUiOverlayStyle.dark.copyWith(statusBarColor: Colors.transparent),
      child: PopScope(
        canPop: _stage == 1,
        onPopInvokedWithResult: (didPop, _) {
          if (!didPop) _goBack();
        },
        child: Scaffold(
          backgroundColor: Colors.white,
          body: SafeArea(
            child: KeyedSubtree(key: ValueKey<int>(_stage), child: body),
          ),
          bottomNavigationBar: SafeArea(top: false, child: bottomBar),
        ),
      ),
    );
  }

  Widget _summaryCard({required bool cartIcon, required String subtitle}) {
    final ready = _stage > 1 || _deliveryReady;
    return _CheckoutSummaryCard(
      cartIcon: cartIcon,
      subtitle: subtitle,
      amountLabel: ready ? formatFcfa(_amountToPay) : 'À confirmer',
      expanded: _summaryExpanded,
      onToggle: () => setState(() => _summaryExpanded = !_summaryExpanded),
      lines: _selectedCartLines.toList(growable: false),
      deliveryLabel: _deliveryReady ? formatFcfa(_preview!.deliveryFee) : 'À confirmer',
    );
  }

  Widget _buildDeliveryStage() {
    final saved = _currentSavedAddress;
    return ListView(
      padding: const EdgeInsets.only(bottom: 22),
      children: [
        _CheckoutHeader(stage: 1, onBack: _goBack),
        _summaryCard(cartIcon: false, subtitle: 'Voir détails'),
        _CheckoutSection(
          title: 'Adresse de livraison',
          action: 'Changer',
          onAction: _openAddressSheet,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              if (!_addressConfirmed && _loadingAddresses)
                const Padding(
                  padding: EdgeInsets.symmetric(vertical: 18),
                  child: LinearProgressIndicator(minHeight: 2, color: OvanieColors.orange),
                )
              else if (!_addressConfirmed)
                _AddAddressPrompt(onTap: _openAddressSheet)
              else
                Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    SizedBox(
                      width: 128,
                      height: 108,
                      child: IgnorePointer(
                        child: _CheckoutMapPreview(latitude: _latitude, longitude: _longitude),
                      ),
                    ),
                    const SizedBox(width: 14),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          if (saved?.isDefault == true) ...[
                            const _GreyChip(label: 'Par défaut'),
                            const SizedBox(height: 8),
                          ],
                          Text(
                            _addressTitle,
                            maxLines: 2,
                            overflow: TextOverflow.ellipsis,
                            style: const TextStyle(color: _ckNavy, fontSize: 17, fontWeight: FontWeight.w800),
                          ),
                          const SizedBox(height: 5),
                          Text(
                            [
                              _name.text.trim(),
                              formatCiPhoneDisplay(_phone.text).replaceAll(' ', '\u00A0'),
                            ].where((part) => part.isNotEmpty).join('  •  '),
                            style: const TextStyle(color: _ckMuted, fontSize: 13.5),
                          ),
                          const SizedBox(height: 5),
                          Text(
                            _address.trim().isNotEmpty
                                ? _address.trim()
                                : [_quartier, _commune, _city].where((part) => part.trim().isNotEmpty).join(', '),
                            maxLines: 2,
                            overflow: TextOverflow.ellipsis,
                            style: const TextStyle(color: _ckMuted, fontSize: 13.5, height: 1.35),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
              if (_locationWarning != null) ...[
                const SizedBox(height: 14),
                _LocationWarningBox(message: _locationWarning!, onRetry: _useCurrentPosition),
              ],
            ],
          ),
        ),
        _CheckoutSection(
          title: 'Contact pour la livraison',
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const _FieldLabel('Téléphone'),
              TextField(
                controller: _phone,
                keyboardType: TextInputType.phone,
                inputFormatters: const [CiPhoneInputFormatter(withCountryCode: true)],
                style: const TextStyle(color: _ckNavy, fontSize: 15.5),
                decoration: _ckInputDecoration(
                  hint: '+225 07 00 00 00 00',
                  suffix: const Icon(Icons.call_outlined, color: _ckNavy, size: 22),
                ),
                onChanged: (_) {
                  if (_error != null) setState(() => _error = null);
                },
              ),
              const SizedBox(height: 16),
              const _FieldLabel('Consigne au livreur'),
              TextField(
                controller: _notesController,
                minLines: 2,
                maxLines: 4,
                style: const TextStyle(color: _ckNavy, fontSize: 15),
                decoration: _ckInputDecoration(hint: 'Ex. Appeler avant d’arriver, portail bleu...'),
                onChanged: (value) => _notes = value,
              ),
            ],
          ),
        ),
        _CheckoutSection(
          title: 'Livraison',
          child: _DeliveryDetails(
            preview: _preview,
            calculating: _calculating,
            ready: _deliveryReady,
            addressConfirmed: _addressConfirmed,
            fallbackWeightKg: _selectedCartLines.fold<double>(
              0,
              (sum, line) => sum + line.product.weightKg * line.quantity,
            ),
          ),
        ),
        if (_error != null)
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 12, 16, 0),
            child: _CheckoutError(message: _error!),
          ),
      ],
    );
  }

  Widget _buildPaymentStage() {
    final codEnabled = _cashOnDeliveryEnabled;
    final onlineEnabled = _onlinePaymentEnabled;
    final onlineSelected = _paymentMethod == 'paydunya';
    final codSelected = _paymentMethod == 'cash_on_delivery';
    final operators = _availableOnlineOperators;
    final needsPhone = onlineSelected && _onlineOperator.isNotEmpty && _onlineOperator != 'card';
    final phoneError = _paymentSubmitted && _paymentPhoneInvalid;

    return ListView(
      padding: const EdgeInsets.only(bottom: 22),
      children: [
        _CheckoutHeader(stage: 2, onBack: _goBack),
        _summaryCard(cartIcon: true, subtitle: 'Voir détails'),
        _CheckoutSection(
          title: 'Mode de paiement',
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              if (_paymentSubmitted && _paymentMethodMissing) ...[
                _PaymentMethodMissing(onChoose: () => setState(() => _paymentSubmitted = false)),
                const SizedBox(height: 12),
              ],
              _PaymentModeTile(
                image: 'assets/images/checkout/pay_cash.png',
                title: 'Paiement à la livraison',
                subtitle: 'En espèces, à la réception',
                selected: codSelected,
                enabled: codEnabled,
                onTap: _selectCashOnDelivery,
              ),
              const SizedBox(height: 12),
              _PaymentModeTile(
                image: 'assets/images/checkout/pay_online.png',
                title: 'Paiement en ligne',
                subtitle: 'Mobile Money ou carte bancaire',
                selected: onlineSelected,
                enabled: onlineEnabled,
                onTap: _selectOnlinePayment,
              ),
              if (!codEnabled && (_cashOnDeliveryOption?.reason.trim().isNotEmpty ?? false)) ...[
                const SizedBox(height: 10),
                _CheckoutError(message: _cashOnDeliveryOption!.reason),
              ],
              if (!onlineEnabled && (_onlinePaymentOption?.reason.trim().isNotEmpty ?? false)) ...[
                const SizedBox(height: 10),
                _CheckoutError(message: _onlinePaymentOption!.reason),
              ],
            ],
          ),
        ),
        if (onlineSelected)
          _CheckoutSection(
            title: 'Moyens de paiement en ligne',
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                for (final operator in operators)
                  Padding(
                    padding: const EdgeInsets.only(bottom: 10),
                    child: _OperatorTile(
                      operator: operator,
                      selected: _onlineOperator == operator.code,
                      enabled: onlineEnabled,
                      onTap: () => _selectOnlineOperator(operator),
                    ),
                  ),
                if (needsPhone) ...[
                  const SizedBox(height: 6),
                  const _FieldLabel('Numéro Mobile Money', large: true),
                  _MobileMoneyField(
                    key: ValueKey<String>('payment-phone-$_onlineOperator'),
                    initialValue: formatCiPhoneDisplay(_paymentPhone, includeCountryCode: false),
                    error: phoneError,
                    onChanged: (value) => setState(() {
                      _paymentPhone = normalizeCiPhoneForApi(value);
                      _error = null;
                    }),
                  ),
                  const SizedBox(height: 6),
                  Text(
                    phoneError
                        ? 'Saisissez le numéro Mobile Money ivoirien à 10 chiffres qui sera débité.'
                        : 'Saisissez le numéro qui sera débité pour ce paiement.',
                    style: TextStyle(color: phoneError ? _ckRed : _ckMuted, fontSize: 12.5),
                  ),
                ],
                if (_onlineOperator == 'orange') ...[
                  const SizedBox(height: 18),
                  const _FieldLabel('OTP Orange Money', large: true),
                  TextFormField(
                    key: const ValueKey<String>('orange-money-otp'),
                    initialValue: _orangeOtp,
                    keyboardType: TextInputType.number,
                    textInputAction: TextInputAction.done,
                    style: const TextStyle(color: _ckNavy, fontSize: 15.5),
                    decoration: _ckInputDecoration(hint: 'Saisissez l’OTP si Orange Money vous le demande'),
                    onChanged: (value) => setState(() {
                      _orangeOtp = value.trim();
                      _error = null;
                    }),
                  ),
                  const SizedBox(height: 6),
                  const Text(
                    'Ce code n’est demandé que lorsque l’opérateur Orange l’exige.',
                    style: TextStyle(color: _ckMuted, fontSize: 12.5),
                  ),
                ],
              ],
            ),
          ),
        if (_error != null)
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 12, 16, 0),
            child: _CheckoutError(message: _error!),
          ),
      ],
    );
  }

  Widget _buildConfirmationStage() {
    final preview = _preview;
    final lines = _selectedCartLines.toList(growable: false);
    final count = lines.length;
    final subtotal = preview?.subtotal ?? _selectedSubtotal;
    final delivery = preview?.deliveryFee ?? 0;
    final total = preview?.total ?? subtotal + delivery;
    final discount = math.max(0.0, subtotal + delivery - total);
    final saved = _currentSavedAddress;
    final placeTitle = [
      if (saved != null && saved.label.trim().isNotEmpty) saved.label.trim() else _addressTitle,
      if (_commune.trim().isNotEmpty && _commune.trim() != _addressTitle) _commune.trim(),
    ].join(', ');

    return ListView(
      padding: const EdgeInsets.only(bottom: 22),
      children: [
        _CheckoutHeader(stage: 3, onBack: _goBack),
        _summaryCard(cartIcon: true, subtitle: '$count article${count > 1 ? 's' : ''}'),
        _CheckoutSection(
          title: 'Récapitulatif',
          trailing: Text(
            '$count article${count > 1 ? 's' : ''}',
            style: const TextStyle(color: _ckMuted, fontSize: 14.5),
          ),
          child: Column(
            children: [
              for (var index = 0; index < lines.length; index++) ...[
                _ConfirmationLine(line: lines[index]),
                if (index != lines.length - 1) const Divider(height: 22, color: _ckBorder),
              ],
              const SizedBox(height: 14),
              Container(
                padding: const EdgeInsets.fromLTRB(16, 14, 16, 14),
                decoration: BoxDecoration(color: _ckSoft, borderRadius: BorderRadius.circular(12)),
                child: Column(
                  children: [
                    _TotalsRow(label: 'Total produits', value: formatFcfa(subtotal)),
                    const SizedBox(height: 9),
                    _TotalsRow(label: 'Livraison', value: formatFcfa(delivery)),
                    const SizedBox(height: 9),
                    _TotalsRow(label: 'Remise', value: '- ${formatFcfa(discount)}', valueColor: _ckGreen),
                    const Divider(height: 26, color: Color(0xFFD9DFE8)),
                    Row(
                      children: [
                        const Expanded(
                          child: Text(
                            'Total à payer',
                            style: TextStyle(color: _ckNavy, fontSize: 17, fontWeight: FontWeight.w800),
                          ),
                        ),
                        Flexible(
                          child: FittedBox(
                            fit: BoxFit.scaleDown,
                            child: Text(
                              formatFcfa(total),
                              style: const TextStyle(color: _ckNavy, fontSize: 26, fontWeight: FontWeight.w900),
                            ),
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
        _CheckoutSection(
          title: 'Adresse de livraison',
          action: 'Modifier',
          onAction: () => setState(() => _stage = 1),
          child: Container(
            width: double.infinity,
            padding: const EdgeInsets.fromLTRB(14, 14, 14, 14),
            decoration: BoxDecoration(color: _ckSoft, borderRadius: BorderRadius.circular(12)),
            child: Row(
              children: [
                const Icon(Icons.location_on_rounded, color: Color(0xFF6B7591), size: 30),
                const SizedBox(width: 14),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        placeTitle,
                        style: const TextStyle(color: _ckNavy, fontSize: 15, fontWeight: FontWeight.w700),
                      ),
                      const SizedBox(height: 5),
                      if (_commune.trim().isNotEmpty) _AddressDetail(label: 'Commune', value: _commune),
                      if (_quartier.trim().isNotEmpty) _AddressDetail(label: 'Quartier', value: _quartier),
                      if (_notes.trim().isNotEmpty) _AddressDetail(label: 'Repère', value: _notes),
                      if (_address.trim().isNotEmpty) _AddressDetail(label: 'Adresse complète', value: _address),
                    ],
                  ),
                ),
              ],
            ),
          ),
        ),
        _CheckoutSection(
          title: 'Mode de paiement',
          action: 'Modifier',
          onAction: () => setState(() => _stage = 2),
          child: Column(
            children: [
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(12),
                  border: Border.all(color: _ckBorder),
                ),
                child: Row(
                  children: [
                    if (_paymentMethod == 'cash_on_delivery')
                      ClipRRect(
                        borderRadius: BorderRadius.circular(10),
                        child: Image.asset(
                          'assets/images/checkout/pay_cash.png',
                          width: 64,
                          height: 50,
                          fit: BoxFit.cover,
                        ),
                      )
                    else
                      PaymentOperatorLogo(code: _onlineOperator, size: 54),
                    const SizedBox(width: 16),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            _paymentLabel,
                            style: const TextStyle(color: _ckNavy, fontSize: 16, fontWeight: FontWeight.w700),
                          ),
                          const SizedBox(height: 3),
                          Text(
                            _paymentMethod == 'cash_on_delivery'
                                ? 'En espèces, à la réception'
                                : _onlineOperator == 'card'
                                ? 'Visa, Mastercard et autres'
                                : _maskedPhone(_paymentPhone),
                            style: const TextStyle(color: _ckNavy, fontSize: 14.5),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 12),
              Container(
                width: double.infinity,
                padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
                decoration: BoxDecoration(color: _ckGreenSoft, borderRadius: BorderRadius.circular(10)),
                child: const Row(
                  children: [
                    Icon(Icons.lock_rounded, color: _ckGreen, size: 20),
                    SizedBox(width: 10),
                    Expanded(
                      child: Text(
                        'Vos informations de paiement sont sécurisées et cryptées.',
                        style: TextStyle(color: Color(0xFF15803D), fontSize: 13.5),
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
        if (_error != null)
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 12, 16, 0),
            child: _CheckoutError(message: _error!),
          ),
      ],
    );
  }
}

String _maskedPhone(String raw) {
  final digits = ciLocalPhoneDigits(raw);
  if (digits.length != 10) return formatCiPhoneDisplay(raw, includeCountryCode: false);
  return '${digits.substring(0, 2)} •• •• ${digits.substring(6, 8)} ${digits.substring(8)}';
}

String _capitalized(String value) {
  final clean = value.trim();
  if (clean.isEmpty) return clean;
  return clean[0].toUpperCase() + clean.substring(1);
}

/// Unité de vente sans précision entre parenthèses : « sac (50 kg) » -> « sac ».
String shortSaleUnit(String unit) {
  final clean = unit.replaceAll(RegExp(r'\s*\(.*?\)'), '').trim();
  return clean.isEmpty ? 'unité' : clean;
}

String _groupedNumber(num value) {
  final digits = value.round().abs().toString();
  final buffer = StringBuffer(value < 0 ? '-' : '');
  for (var i = 0; i < digits.length; i++) {
    if (i > 0 && (digits.length - i) % 3 == 0) buffer.write(' ');
    buffer.write(digits[i]);
  }
  return buffer.toString();
}

const Color _ckNavy = Color(0xFF0A1B4F);
const Color _ckMuted = Color(0xFF6B7591);
const Color _ckBorder = Color(0xFFE3E8F0);
const Color _ckSoft = Color(0xFFF1F4F8);
const Color _ckBlue = Color(0xFF0B5ED7);
const Color _ckGreen = Color(0xFF16A34A);
const Color _ckGreenSoft = Color(0xFFE6F6EC);
const Color _ckRed = Color(0xFFE5231B);

InputDecoration _ckInputDecoration({String? hint, Widget? suffix, bool error = false, bool enabled = true}) {
  OutlineInputBorder border(Color color, [double width = 1]) => OutlineInputBorder(
    borderRadius: BorderRadius.circular(10),
    borderSide: BorderSide(color: color, width: width),
  );
  return InputDecoration(
    hintText: hint,
    hintStyle: const TextStyle(color: Color(0xFF8A93A8), fontSize: 14.5),
    suffixIcon: suffix,
    filled: true,
    fillColor: enabled ? Colors.white : const Color(0xFFEFF2F6),
    isDense: true,
    contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 15),
    border: border(const Color(0xFFD5DCE6)),
    enabledBorder: border(error ? _ckRed : const Color(0xFFD5DCE6), error ? 1.4 : 1),
    disabledBorder: border(const Color(0xFFE1E6EE)),
    focusedBorder: border(error ? _ckRed : OvanieColors.orange, 1.4),
  );
}

/// Pastille verte « Paiement sécurisé » de l'en-tête.
class _CheckoutHeader extends StatelessWidget {
  final int stage;
  final VoidCallback onBack;

  const _CheckoutHeader({required this.stage, required this.onBack});

  @override
  Widget build(BuildContext context) {
    if (stage == 1) {
      return Column(
        children: [
          SizedBox(
            height: 60,
            child: Row(
              children: [
                IconButton(
                  onPressed: onBack,
                  tooltip: 'Retour',
                  icon: const Icon(Icons.chevron_left_rounded, color: _ckNavy, size: 34),
                ),
                const Expanded(
                  child: FittedBox(
                    fit: BoxFit.scaleDown,
                    child: Text(
                      'Finaliser la commande',
                      maxLines: 1,
                      style: TextStyle(color: _ckNavy, fontSize: 19, fontWeight: FontWeight.w800),
                    ),
                  ),
                ),
                const SizedBox(width: 6),
                const PaymentSecureChip(),
                const SizedBox(width: 12),
              ],
            ),
          ),
          const Padding(padding: EdgeInsets.fromLTRB(16, 4, 16, 14), child: _InlineStepper()),
        ],
      );
    }
    return Column(
      children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(4, 8, 12, 0),
          child: Row(
            children: [
              IconButton(
                onPressed: onBack,
                tooltip: 'Retour',
                icon: const Icon(Icons.arrow_back_rounded, color: _ckNavy, size: 28),
              ),
              const SizedBox(width: 2),
              const Expanded(
                child: FittedBox(
                  fit: BoxFit.scaleDown,
                  alignment: Alignment.centerLeft,
                  child: Text(
                    'Finaliser la commande',
                    style: TextStyle(color: _ckNavy, fontSize: 22, fontWeight: FontWeight.w800),
                  ),
                ),
              ),
              const SizedBox(width: 8),
              const PaymentSecureChip(),
            ],
          ),
        ),
        Padding(
          padding: const EdgeInsets.fromLTRB(12, 12, 12, 14),
          child: _StackedStepper(stage: stage),
        ),
      ],
    );
  }
}

/// Étapes sur une ligne (écran Livraison).
class _InlineStepper extends StatelessWidget {
  const _InlineStepper();

  @override
  Widget build(BuildContext context) {
    Widget step(int number, String label, bool active) => Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Container(
          width: 34,
          height: 34,
          alignment: Alignment.center,
          decoration: BoxDecoration(
            shape: BoxShape.circle,
            color: active ? OvanieColors.orange : const Color(0xFFEDF0F5),
          ),
          child: Text(
            '$number',
            style: TextStyle(
              color: active ? Colors.white : const Color(0xFF6B7591),
              fontSize: 15,
              fontWeight: FontWeight.w700,
            ),
          ),
        ),
        const SizedBox(width: 8),
        Text(
          label,
          style: TextStyle(
            color: active ? _ckNavy : const Color(0xFF6B7591),
            fontSize: 14,
            fontWeight: active ? FontWeight.w700 : FontWeight.w500,
          ),
        ),
      ],
    );
    Widget line() => Expanded(
      child: Container(height: 1.5, margin: const EdgeInsets.symmetric(horizontal: 8), color: const Color(0xFFD5DBE5)),
    );
    return FittedBox(
      fit: BoxFit.scaleDown,
      child: SizedBox(
        width: 380,
        child: Row(
          children: [
            step(1, 'Livraison', true),
            line(),
            step(2, 'Paiement', false),
            line(),
            step(3, 'Confirmation', false),
          ],
        ),
      ),
    );
  }
}

/// Étapes avec libellés sous les pastilles (écrans Paiement et Confirmation).
class _StackedStepper extends StatelessWidget {
  final int stage;

  const _StackedStepper({required this.stage});

  @override
  Widget build(BuildContext context) {
    const labels = ['Livraison', 'Paiement', 'Confirmation'];
    Widget connector(int from) {
      final done = stage > from + 1;
      final reaching = stage == from + 1;
      return Expanded(
        child: Container(
          height: 2.5,
          decoration: BoxDecoration(
            color: done ? _ckGreen : (reaching ? null : const Color(0xFFDDE2EA)),
            gradient: reaching ? const LinearGradient(colors: [_ckGreen, _ckGreen, OvanieColors.orange]) : null,
          ),
        ),
      );
    }

    Widget dot(int number) {
      final done = number < stage;
      final active = number == stage;
      return Container(
        width: 42,
        height: 42,
        alignment: Alignment.center,
        decoration: BoxDecoration(
          shape: BoxShape.circle,
          color: done ? _ckGreen : (active ? OvanieColors.orange : const Color(0xFFEDF0F5)),
        ),
        child: done
            ? const Icon(Icons.check_rounded, color: Colors.white, size: 24)
            : Text(
                '$number',
                style: TextStyle(
                  color: active ? Colors.white : const Color(0xFF8A93A8),
                  fontSize: 17,
                  fontWeight: FontWeight.w700,
                ),
              ),
      );
    }

    return Column(
      children: [
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: 34),
          child: Row(children: [dot(1), connector(1), dot(2), connector(2), dot(3)]),
        ),
        const SizedBox(height: 8),
        Row(
          children: [
            for (var i = 0; i < labels.length; i++)
              Expanded(
                child: Text(
                  labels[i],
                  textAlign: TextAlign.center,
                  style: TextStyle(
                    color: i + 1 < stage ? _ckGreen : (i + 1 == stage ? OvanieColors.orange : const Color(0xFF8A93A8)),
                    fontSize: 15,
                    fontWeight: FontWeight.w500,
                  ),
                ),
              ),
          ],
        ),
      ],
    );
  }
}

class _CheckoutSummaryCard extends StatelessWidget {
  final bool cartIcon;
  final String subtitle;
  final String amountLabel;
  final bool expanded;
  final VoidCallback onToggle;
  final List<CartLine> lines;
  final String deliveryLabel;

  const _CheckoutSummaryCard({
    required this.cartIcon,
    required this.subtitle,
    required this.amountLabel,
    required this.expanded,
    required this.onToggle,
    required this.lines,
    required this.deliveryLabel,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.symmetric(horizontal: 16),
      decoration: BoxDecoration(color: _ckSoft, borderRadius: BorderRadius.circular(14)),
      child: Material(
        color: Colors.transparent,
        child: InkWell(
          onTap: onToggle,
          borderRadius: BorderRadius.circular(14),
          child: Padding(
            padding: const EdgeInsets.fromLTRB(18, 16, 18, 16),
            child: Column(
              children: [
                Row(
                  children: [
                    if (cartIcon) ...[
                      const Icon(Icons.shopping_cart_outlined, color: _ckNavy, size: 28),
                      const SizedBox(width: 16),
                    ],
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          FittedBox(
                            fit: BoxFit.scaleDown,
                            alignment: Alignment.centerLeft,
                            child: Text(
                              'Récapitulatif de la commande',
                              maxLines: 1,
                              style: TextStyle(
                                color: _ckNavy,
                                fontSize: cartIcon ? 15.5 : 17,
                                fontWeight: cartIcon ? FontWeight.w600 : FontWeight.w800,
                              ),
                            ),
                          ),
                          const SizedBox(height: 3),
                          Row(
                            children: [
                              Text(subtitle, style: const TextStyle(color: _ckMuted, fontSize: 14.5)),
                              const SizedBox(width: 4),
                              Icon(
                                expanded ? Icons.keyboard_arrow_up_rounded : Icons.keyboard_arrow_down_rounded,
                                color: _ckMuted,
                                size: 22,
                              ),
                            ],
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(width: 10),
                    Text(
                      amountLabel,
                      style: TextStyle(color: _ckNavy, fontSize: cartIcon ? 17.5 : 19, fontWeight: FontWeight.w800),
                    ),
                  ],
                ),
                if (expanded) ...[
                  const Divider(height: 24, color: Color(0xFFDCE2EA)),
                  for (final line in lines)
                    Padding(
                      padding: const EdgeInsets.only(bottom: 7),
                      child: Row(
                        children: [
                          Expanded(
                            child: Text(
                              '${line.product.name}  ×${line.quantity}',
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                              style: const TextStyle(color: _ckNavy, fontSize: 13.5),
                            ),
                          ),
                          const SizedBox(width: 8),
                          Text(
                            formatFcfa(line.subtotal),
                            style: const TextStyle(color: _ckNavy, fontSize: 13.5, fontWeight: FontWeight.w600),
                          ),
                        ],
                      ),
                    ),
                  Row(
                    children: [
                      const Expanded(
                        child: Text('Livraison', style: TextStyle(color: _ckMuted, fontSize: 13.5)),
                      ),
                      Text(deliveryLabel, style: const TextStyle(color: _ckMuted, fontSize: 13.5)),
                    ],
                  ),
                ],
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _CheckoutSection extends StatelessWidget {
  final String title;
  final Widget child;
  final String? action;
  final VoidCallback? onAction;
  final Widget? trailing;

  const _CheckoutSection({required this.title, required this.child, this.action, this.onAction, this.trailing});

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.fromLTRB(16, 14, 16, 0),
      padding: const EdgeInsets.fromLTRB(16, 16, 16, 18),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: _ckBorder),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Text(
                  title,
                  style: const TextStyle(color: _ckNavy, fontSize: 19, fontWeight: FontWeight.w800),
                ),
              ),
              if (trailing != null) trailing!,
              if (action != null)
                InkWell(
                  onTap: onAction,
                  borderRadius: BorderRadius.circular(6),
                  child: Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 2, vertical: 4),
                    child: Text(
                      action!,
                      style: const TextStyle(color: _ckBlue, fontSize: 15, fontWeight: FontWeight.w500),
                    ),
                  ),
                ),
            ],
          ),
          const SizedBox(height: 14),
          child,
        ],
      ),
    );
  }
}

class _FieldLabel extends StatelessWidget {
  final String text;
  final bool large;

  const _FieldLabel(this.text, {this.large = false});

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.only(bottom: 8),
    child: Text(
      text,
      style: TextStyle(
        color: _ckNavy,
        fontSize: large ? 17 : 14.5,
        fontWeight: large ? FontWeight.w700 : FontWeight.w500,
      ),
    ),
  );
}

class _GreyChip extends StatelessWidget {
  final String label;

  const _GreyChip({required this.label});

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
    decoration: BoxDecoration(color: const Color(0xFFEDF0F4), borderRadius: BorderRadius.circular(8)),
    child: Text(label, style: const TextStyle(color: Color(0xFF4A556E), fontSize: 12.5)),
  );
}

class _AddAddressPrompt extends StatelessWidget {
  final VoidCallback onTap;

  const _AddAddressPrompt({required this.onTap});

  @override
  Widget build(BuildContext context) => InkWell(
    onTap: onTap,
    borderRadius: BorderRadius.circular(12),
    child: Container(
      width: double.infinity,
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 16),
      decoration: BoxDecoration(
        color: const Color(0xFFFFF6F0),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: OvanieColors.orange.withValues(alpha: .45)),
      ),
      child: const Row(
        children: [
          Icon(Icons.add_location_alt_outlined, color: OvanieColors.orange, size: 28),
          SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Ajouter une adresse de livraison',
                  style: TextStyle(color: _ckNavy, fontSize: 15, fontWeight: FontWeight.w700),
                ),
                SizedBox(height: 3),
                Text(
                  'Rechercher, utiliser ma position ou saisir l’adresse',
                  style: TextStyle(color: _ckMuted, fontSize: 13),
                ),
              ],
            ),
          ),
          Icon(Icons.chevron_right_rounded, color: _ckNavy),
        ],
      ),
    ),
  );
}

/// Encadré jaune « position trop imprécise » avec lien « Réessayer ».
class _LocationWarningBox extends StatelessWidget {
  final String message;
  final VoidCallback onRetry;

  const _LocationWarningBox({required this.message, required this.onRetry});

  @override
  Widget build(BuildContext context) => Container(
    width: double.infinity,
    padding: const EdgeInsets.fromLTRB(14, 14, 14, 12),
    decoration: BoxDecoration(
      color: const Color(0xFFFFF8E6),
      borderRadius: BorderRadius.circular(12),
      border: Border.all(color: const Color(0xFFF5D27A)),
    ),
    child: Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Icon(Icons.warning_rounded, color: Color(0xFFF59E0B), size: 30),
        const SizedBox(width: 14),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(message, style: const TextStyle(color: Color(0xFFC2410C), fontSize: 14, height: 1.4)),
              const SizedBox(height: 6),
              InkWell(
                onTap: onRetry,
                child: const Text(
                  'Réessayer',
                  style: TextStyle(
                    color: Color(0xFFEA580C),
                    fontSize: 14.5,
                    fontWeight: FontWeight.w700,
                    decoration: TextDecoration.underline,
                    decorationColor: Color(0xFFEA580C),
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

class _DeliveryDetails extends StatelessWidget {
  final CheckoutPreview? preview;
  final bool calculating;
  final bool ready;
  final bool addressConfirmed;
  final double fallbackWeightKg;

  const _DeliveryDetails({
    required this.preview,
    required this.calculating,
    required this.ready,
    required this.addressConfirmed,
    required this.fallbackWeightKg,
  });

  String _value(String Function(CheckoutPreview preview) read) {
    if (calculating) return 'Calcul...';
    final current = preview;
    if (current == null) return '—';
    final value = read(current).trim();
    return value.isEmpty ? '—' : value;
  }

  @override
  Widget build(BuildContext context) {
    final weight = preview?.totalWeightKg ?? 0;
    final shownWeight = weight > 0 ? weight : fallbackWeightKg;

    final Widget status;
    if (calculating) {
      status = const Row(
        children: [
          SizedBox(
            width: 34,
            height: 34,
            child: CircularProgressIndicator(strokeWidth: 3.2, color: OvanieColors.orange),
          ),
          SizedBox(width: 16),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Calcul de livraison en cours',
                  style: TextStyle(color: _ckNavy, fontSize: 16, fontWeight: FontWeight.w800),
                ),
                SizedBox(height: 2),
                Text('Nous vérifions votre zone de livraison.', style: TextStyle(color: _ckMuted, fontSize: 13.5)),
              ],
            ),
          ),
        ],
      );
    } else if (ready) {
      status = Row(
        children: [
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 9),
            decoration: BoxDecoration(color: _ckGreenSoft, borderRadius: BorderRadius.circular(10)),
            child: const Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                Icon(Icons.check_circle_rounded, color: _ckGreen, size: 22),
                SizedBox(width: 8),
                Text(
                  'Livraison disponible',
                  style: TextStyle(color: Color(0xFF15803D), fontSize: 14.5, fontWeight: FontWeight.w700),
                ),
              ],
            ),
          ),
          const SizedBox(width: 12),
          const Expanded(
            child: Text('Calculée selon votre adresse chantier', style: TextStyle(color: _ckMuted, fontSize: 13)),
          ),
        ],
      );
    } else if (preview != null) {
      status = Container(
        width: double.infinity,
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(color: const Color(0xFFFFF6EA), borderRadius: BorderRadius.circular(10)),
        child: Text(
          preview!.deliveryMessage.trim().isNotEmpty
              ? preview!.deliveryMessage
              : 'La livraison doit être confirmée pour cette adresse.',
          style: const TextStyle(color: Color(0xFFB45309), fontSize: 13.5, height: 1.35),
        ),
      );
    } else {
      status = Text(
        addressConfirmed
            ? 'La livraison sera calculée pour cette adresse.'
            : 'Choisissez une adresse pour calculer la livraison.',
        style: const TextStyle(color: _ckMuted, fontSize: 13.5),
      );
    }

    final distance = preview?.deliveryDistanceKm;
    final delay = preview?.averageDelayHours ?? 0;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        status,
        const SizedBox(height: 16),
        Row(
          children: [
            Expanded(
              child: _MetricTile(
                icon: Icons.storefront_outlined,
                label: 'Depuis la boutique',
                value: calculating
                    ? 'Calcul...'
                    : (distance != null ? '${distance.toStringAsFixed(1).replaceAll('.', ',')} km' : '—'),
              ),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: _MetricTile(
                icon: Icons.location_on_outlined,
                label: 'Zone',
                value: _value(
                  (p) => p.territoryZoneName.isNotEmpty
                      ? _capitalized(p.territoryZoneName)
                      : (p.deliveryCommune.isNotEmpty ? p.deliveryCommune : p.deliveryCity),
                ),
              ),
            ),
          ],
        ),
        const SizedBox(height: 12),
        Row(
          children: [
            Expanded(
              child: _MetricTile(
                icon: Icons.local_shipping_outlined,
                label: 'Véhicule estimé',
                value: _value((p) => p.recommendedVehicle),
              ),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: _MetricTile(
                icon: Icons.schedule_rounded,
                label: 'Livraison estimée',
                value: calculating ? 'Calcul...' : (preview == null ? '—' : (delay > 0 ? '$delay h' : '24 à 48 h')),
              ),
            ),
          ],
        ),
        const Divider(height: 30, color: _ckBorder),
        Row(
          children: [
            const Expanded(
              child: Text('Poids total', style: TextStyle(color: _ckNavy, fontSize: 15)),
            ),
            Text(
              shownWeight > 0 ? '${_groupedNumber(shownWeight)} kg' : '—',
              style: const TextStyle(color: _ckNavy, fontSize: 16, fontWeight: FontWeight.w800),
            ),
          ],
        ),
        const SizedBox(height: 8),
        const Text(
          'Les frais de livraison sont calculés pour l’adresse renseignée.',
          style: TextStyle(color: _ckMuted, fontSize: 13),
        ),
      ],
    );
  }
}

class _MetricTile extends StatelessWidget {
  final IconData icon;
  final String label;
  final String value;

  const _MetricTile({required this.icon, required this.label, required this.value});

  @override
  Widget build(BuildContext context) => Container(
    height: 80,
    padding: const EdgeInsets.symmetric(horizontal: 12),
    decoration: BoxDecoration(
      color: Colors.white,
      borderRadius: BorderRadius.circular(12),
      border: Border.all(color: _ckBorder),
    ),
    child: Row(
      children: [
        Icon(icon, color: _ckNavy, size: 26),
        const SizedBox(width: 10),
        Expanded(
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              FittedBox(
                fit: BoxFit.scaleDown,
                alignment: Alignment.centerLeft,
                child: Text(label, maxLines: 1, style: const TextStyle(color: _ckMuted, fontSize: 12.5)),
              ),
              const SizedBox(height: 3),
              FittedBox(
                fit: BoxFit.scaleDown,
                alignment: Alignment.centerLeft,
                child: Text(
                  value,
                  maxLines: 1,
                  style: const TextStyle(color: _ckNavy, fontSize: 15, fontWeight: FontWeight.w800),
                ),
              ),
            ],
          ),
        ),
      ],
    ),
  );
}

class _PaymentMethodMissing extends StatelessWidget {
  final VoidCallback onChoose;

  const _PaymentMethodMissing({required this.onChoose});

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.fromLTRB(14, 14, 14, 14),
    decoration: BoxDecoration(
      color: const Color(0xFFFFEEEE),
      borderRadius: BorderRadius.circular(12),
      border: Border.all(color: const Color(0xFFF7B7B7)),
    ),
    child: Column(
      children: [
        const Row(
          children: [
            Icon(Icons.error_rounded, color: _ckRed, size: 24),
            SizedBox(width: 10),
            Expanded(
              child: Text(
                'Choisissez un mode de paiement avant de continuer.',
                style: TextStyle(color: _ckRed, fontSize: 14.5, fontWeight: FontWeight.w500),
              ),
            ),
          ],
        ),
        const SizedBox(height: 12),
        SizedBox(
          width: double.infinity,
          height: 44,
          child: OutlinedButton(
            onPressed: onChoose,
            style: OutlinedButton.styleFrom(
              foregroundColor: _ckNavy,
              backgroundColor: Colors.white,
              side: const BorderSide(color: _ckNavy, width: 1.3),
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
            ),
            child: const Text(
              'Choisir un mode de paiement',
              style: TextStyle(fontSize: 15, fontWeight: FontWeight.w700),
            ),
          ),
        ),
      ],
    ),
  );
}

class _RadioDot extends StatelessWidget {
  final bool selected;

  const _RadioDot({required this.selected});

  @override
  Widget build(BuildContext context) => Container(
    width: 26,
    height: 26,
    decoration: BoxDecoration(
      shape: BoxShape.circle,
      color: Colors.white,
      border: Border.all(color: selected ? OvanieColors.orange : const Color(0xFFB9C2D0), width: selected ? 2 : 1.5),
    ),
    alignment: Alignment.center,
    child: selected
        ? Container(
            width: 14,
            height: 14,
            decoration: const BoxDecoration(color: OvanieColors.orange, shape: BoxShape.circle),
          )
        : null,
  );
}

BoxDecoration _choiceDecoration(bool selected) => BoxDecoration(
  color: selected ? const Color(0xFFFFF6F0) : Colors.white,
  borderRadius: BorderRadius.circular(12),
  border: Border.all(color: selected ? OvanieColors.orange : _ckBorder, width: selected ? 1.6 : 1),
);

class _PaymentModeTile extends StatelessWidget {
  final String image;
  final String title;
  final String subtitle;
  final bool selected;
  final bool enabled;
  final VoidCallback onTap;

  const _PaymentModeTile({
    required this.image,
    required this.title,
    required this.subtitle,
    required this.selected,
    required this.enabled,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) => Opacity(
    opacity: enabled ? 1 : .5,
    child: InkWell(
      onTap: enabled ? onTap : null,
      borderRadius: BorderRadius.circular(12),
      child: Container(
        padding: const EdgeInsets.fromLTRB(14, 10, 12, 10),
        decoration: _choiceDecoration(selected),
        child: Row(
          children: [
            _RadioDot(selected: selected),
            const SizedBox(width: 14),
            ClipRRect(
              borderRadius: BorderRadius.circular(10),
              child: Image.asset(image, width: 86, height: 66, fit: BoxFit.cover),
            ),
            const SizedBox(width: 14),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  FittedBox(
                    fit: BoxFit.scaleDown,
                    alignment: Alignment.centerLeft,
                    child: Text(
                      title,
                      maxLines: 1,
                      style: const TextStyle(color: _ckNavy, fontSize: 16, fontWeight: FontWeight.w700),
                    ),
                  ),
                  const SizedBox(height: 4),
                  Text(subtitle, style: const TextStyle(color: _ckMuted, fontSize: 13.5)),
                ],
              ),
            ),
          ],
        ),
      ),
    ),
  );
}

/// Logo rond de l'opérateur, comme sur les maquettes de paiement.
class _OperatorTile extends StatelessWidget {
  final CheckoutPaymentOperatorOption operator;
  final bool selected;
  final bool enabled;
  final VoidCallback onTap;

  const _OperatorTile({required this.operator, required this.selected, required this.enabled, required this.onTap});

  String get _subtitle {
    switch (operator.code) {
      case 'wave':
        return 'Payez avec votre compte Wave';
      case 'orange':
        return 'Payez avec Orange Money';
      case 'mtn':
        return 'Payez avec MTN Mobile Money';
      case 'moov':
        return 'Payez avec Moov Money';
      case 'card':
        return 'Visa, Mastercard et autres';
      default:
        return 'Paiement sécurisé';
    }
  }

  @override
  Widget build(BuildContext context) => Opacity(
    opacity: enabled ? 1 : .5,
    child: InkWell(
      onTap: enabled ? onTap : null,
      borderRadius: BorderRadius.circular(12),
      child: Container(
        padding: const EdgeInsets.fromLTRB(14, 9, 12, 9),
        decoration: _choiceDecoration(selected),
        child: Row(
          children: [
            _RadioDot(selected: selected),
            const SizedBox(width: 14),
            SizedBox(
              width: 82,
              child: Center(child: PaymentOperatorLogo(code: operator.code, size: 50)),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    operator.label,
                    style: const TextStyle(color: _ckNavy, fontSize: 15.5, fontWeight: FontWeight.w700),
                  ),
                  const SizedBox(height: 2),
                  Text(_subtitle, style: const TextStyle(color: _ckMuted, fontSize: 13)),
                ],
              ),
            ),
          ],
        ),
      ),
    ),
  );
}

/// Champ « +225 ⌄ | numéro » des maquettes de paiement.
class _MobileMoneyField extends StatelessWidget {
  final String initialValue;
  final bool error;
  final ValueChanged<String> onChanged;

  const _MobileMoneyField({super.key, required this.initialValue, required this.error, required this.onChanged});

  @override
  Widget build(BuildContext context) {
    final borderColor = error ? _ckRed : const Color(0xFFD5DCE6);
    return Container(
      height: 56,
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(10),
        border: Border.all(color: borderColor, width: error ? 1.4 : 1),
      ),
      child: Row(
        children: [
          const SizedBox(width: 16),
          const Text('+225', style: TextStyle(color: _ckNavy, fontSize: 16)),
          const SizedBox(width: 6),
          const Icon(Icons.keyboard_arrow_down_rounded, color: _ckMuted, size: 22),
          const SizedBox(width: 10),
          Container(width: 1, color: borderColor),
          Expanded(
            child: TextFormField(
              initialValue: initialValue,
              keyboardType: TextInputType.phone,
              textInputAction: TextInputAction.done,
              autofillHints: const [AutofillHints.telephoneNumber],
              inputFormatters: const [CiPhoneInputFormatter()],
              style: const TextStyle(color: _ckNavy, fontSize: 16.5),
              decoration: const InputDecoration(
                hintText: '07 00 00 00 00',
                hintStyle: TextStyle(color: Color(0xFF8A93A8)),
                border: InputBorder.none,
                enabledBorder: InputBorder.none,
                focusedBorder: InputBorder.none,
                filled: false,
                contentPadding: EdgeInsets.symmetric(horizontal: 16),
              ),
              onChanged: onChanged,
            ),
          ),
          if (error) ...[const Icon(Icons.error_rounded, color: _ckRed, size: 24), const SizedBox(width: 14)],
        ],
      ),
    );
  }
}

class _ConfirmationLine extends StatelessWidget {
  final CartLine line;

  const _ConfirmationLine({required this.line});

  @override
  Widget build(BuildContext context) {
    final product = line.product;
    final unit = shortSaleUnit(product.unitLabel);
    final subtitle =
        line.variant?.label ??
        (product.packaging.trim().isNotEmpty
            ? product.packaging.trim()
            : (product.weightKg > 0 ? '${_groupedNumber(product.weightKg)} kg' : unit));
    return Row(
      children: [
        ClipRRect(
          borderRadius: BorderRadius.circular(10),
          child: Container(
            width: 62,
            height: 58,
            color: const Color(0xFFF3F4F6),
            child: line.imageUrl.trim().isEmpty
                ? const Icon(Icons.inventory_2_outlined, color: Color(0xFFB9C2D2))
                : Image.network(
                    line.imageUrl,
                    fit: BoxFit.cover,
                    cacheWidth: 220,
                    errorBuilder: (_, __, ___) => const Icon(Icons.broken_image_outlined, color: Color(0xFFB9C2D2)),
                  ),
          ),
        ),
        const SizedBox(width: 14),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                product.name,
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(color: _ckNavy, fontSize: 14.5, fontWeight: FontWeight.w600),
              ),
              const SizedBox(height: 3),
              Text(
                subtitle,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(color: _ckMuted, fontSize: 13),
              ),
            ],
          ),
        ),
        const SizedBox(width: 8),
        Text('×${line.quantity}', style: const TextStyle(color: _ckNavy, fontSize: 14.5)),
        const SizedBox(width: 14),
        Column(
          crossAxisAlignment: CrossAxisAlignment.end,
          children: [
            Text(
              formatFcfa(line.subtotal),
              style: const TextStyle(color: _ckNavy, fontSize: 15, fontWeight: FontWeight.w700),
            ),
            const SizedBox(height: 3),
            Text('${formatFcfa(line.unitPrice)} / $unit', style: const TextStyle(color: _ckMuted, fontSize: 12)),
          ],
        ),
      ],
    );
  }
}

class _TotalsRow extends StatelessWidget {
  final String label;
  final String value;
  final Color valueColor;

  const _TotalsRow({required this.label, required this.value, this.valueColor = _ckNavy});

  @override
  Widget build(BuildContext context) => Row(
    children: [
      Expanded(
        child: Text(label, style: const TextStyle(color: _ckMuted, fontSize: 15)),
      ),
      Text(value, style: TextStyle(color: valueColor, fontSize: 15)),
    ],
  );
}

class _AddressDetail extends StatelessWidget {
  final String label;
  final String value;

  const _AddressDetail({required this.label, required this.value});

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.only(top: 3),
    child: Text.rich(
      TextSpan(
        children: [
          TextSpan(
            text: '$label : ',
            style: const TextStyle(color: Color(0xFF4A556E), fontWeight: FontWeight.w700),
          ),
          TextSpan(text: value.trim()),
        ],
      ),
      style: const TextStyle(color: _ckMuted, fontSize: 13.5, height: 1.35),
    ),
  );
}

class _CheckoutTotalBar extends StatelessWidget {
  final String amountLabel;
  final String buttonLabel;
  final bool loading;
  final VoidCallback onPressed;

  const _CheckoutTotalBar({
    required this.amountLabel,
    required this.buttonLabel,
    required this.loading,
    required this.onPressed,
  });

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.fromLTRB(20, 12, 16, 12),
    decoration: const BoxDecoration(
      color: Colors.white,
      border: Border(top: BorderSide(color: _ckBorder)),
      boxShadow: [BoxShadow(color: Color(0x0F000000), blurRadius: 12, offset: Offset(0, -3))],
    ),
    child: Row(
      children: [
        Expanded(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Text('Total à payer', style: TextStyle(color: _ckMuted, fontSize: 14)),
              FittedBox(
                fit: BoxFit.scaleDown,
                alignment: Alignment.centerLeft,
                child: Text(
                  amountLabel,
                  style: const TextStyle(color: _ckNavy, fontSize: 24, fontWeight: FontWeight.w900),
                ),
              ),
            ],
          ),
        ),
        const SizedBox(width: 12),
        SizedBox(
          width: (MediaQuery.sizeOf(context).width * .5).clamp(150.0, 210.0),
          height: 54,
          child: FilledButton(
            onPressed: loading ? null : onPressed,
            style: FilledButton.styleFrom(
              elevation: 0,
              backgroundColor: OvanieColors.orange,
              disabledBackgroundColor: OvanieColors.orange.withValues(alpha: .6),
              foregroundColor: Colors.white,
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
            ),
            child: loading
                ? const SizedBox(
                    width: 22,
                    height: 22,
                    child: CircularProgressIndicator(strokeWidth: 2.4, color: Colors.white),
                  )
                : FittedBox(
                    fit: BoxFit.scaleDown,
                    child: Text(buttonLabel, style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w700)),
                  ),
          ),
        ),
      ],
    ),
  );
}

class _CheckoutWideBar extends StatelessWidget {
  final String label;
  final bool loading;
  final VoidCallback onPressed;

  const _CheckoutWideBar({required this.label, required this.loading, required this.onPressed});

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.fromLTRB(16, 12, 16, 12),
    decoration: const BoxDecoration(
      color: Colors.white,
      border: Border(top: BorderSide(color: _ckBorder)),
    ),
    child: SizedBox(
      height: 56,
      child: FilledButton(
        onPressed: loading ? null : onPressed,
        style: FilledButton.styleFrom(
          elevation: 0,
          backgroundColor: OvanieColors.orange,
          disabledBackgroundColor: OvanieColors.orange.withValues(alpha: .6),
          foregroundColor: Colors.white,
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
        ),
        child: loading
            ? const SizedBox(
                width: 22,
                height: 22,
                child: CircularProgressIndicator(strokeWidth: 2.4, color: Colors.white),
              )
            : Text(label, style: const TextStyle(fontSize: 17, fontWeight: FontWeight.w700)),
      ),
    ),
  );
}

/// Fenêtre « Autoriser la localisation » (maquette du tunnel de commande).
Future<bool> showLocationPermissionDialog(BuildContext context, DeviceLocationException error) async {
  final openDeviceSettings = error.reason == DeviceLocationFailureReason.serviceDisabled;
  final openAppSettings = error.reason == DeviceLocationFailureReason.permissionDeniedForever;
  final retry = await showDialog<bool>(
    context: context,
    builder: (dialogContext) => Dialog(
      backgroundColor: Colors.white,
      surfaceTintColor: Colors.white,
      insetPadding: const EdgeInsets.symmetric(horizontal: 36),
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(22)),
      child: Padding(
        padding: const EdgeInsets.fromLTRB(22, 26, 22, 10),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              width: 78,
              height: 78,
              decoration: const BoxDecoration(color: Color(0xFFFFEDE2), shape: BoxShape.circle),
              child: const Icon(Icons.location_on_rounded, color: OvanieColors.orange, size: 42),
            ),
            const SizedBox(height: 18),
            const Text(
              'Autoriser la localisation',
              textAlign: TextAlign.center,
              style: TextStyle(color: _ckNavy, fontSize: 21, fontWeight: FontWeight.w900),
            ),
            const SizedBox(height: 10),
            Text(
              openDeviceSettings
                  ? 'La localisation du téléphone est désactivée. Activez-la puis revenez dans OVANIE.'
                  : 'OVANIE a besoin de votre position uniquement pour renseigner votre adresse de livraison. Activez « Localisation » et « Position précise », puis revenez dans l’application.',
              textAlign: TextAlign.center,
              style: const TextStyle(color: Color(0xFF4A556E), fontSize: 15, height: 1.45),
            ),
            const SizedBox(height: 22),
            SizedBox(
              width: double.infinity,
              height: 50,
              child: FilledButton(
                onPressed: () async {
                  Navigator.of(dialogContext).pop(!openDeviceSettings && !openAppSettings);
                  if (openDeviceSettings) {
                    await DeviceLocation.openDeviceLocationSettings();
                  } else if (openAppSettings) {
                    await DeviceLocation.openAppLocationSettings();
                  }
                },
                style: FilledButton.styleFrom(
                  elevation: 0,
                  backgroundColor: OvanieColors.orange,
                  foregroundColor: Colors.white,
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                ),
                child: Text(
                  openDeviceSettings || openAppSettings ? 'Ouvrir les paramètres' : 'Réessayer',
                  style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w700),
                ),
              ),
            ),
            const SizedBox(height: 4),
            TextButton(
              onPressed: () => Navigator.of(dialogContext).pop(false),
              child: const Text('Plus tard', style: TextStyle(color: Color(0xFF8A93A8), fontSize: 15.5)),
            ),
          ],
        ),
      ),
    ),
  );
  return retry == true;
}

bool isLocationPermissionProblem(String message) {
  final value = message.toLowerCase();
  return value.contains('autorisation') ||
      value.contains('localisation refusée') ||
      value.contains('localisation refusee') ||
      value.contains('bloquée') ||
      value.contains('bloquee') ||
      value.contains('activez la localisation du téléphone');
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
    final center = _hasPosition ? LatLng(latitude!, longitude!) : const LatLng(5.359952, -4.008256);
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
            interactionOptions: const InteractionOptions(flags: InteractiveFlag.all & ~InteractiveFlag.rotate),
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
                      shadows: [Shadow(color: Color(0x42000000), blurRadius: 5)],
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

/// Feuille de choix (commune, quartier…) partagée avec le carnet d'adresses.
Future<String?> showLocationPickerSheet(
  BuildContext context, {
  required String title,
  required List<String> items,
  String? selected,
}) {
  return showModalBottomSheet<String>(
    context: context,
    isScrollControlled: true,
    useSafeArea: true,
    backgroundColor: Colors.transparent,
    builder: (_) => _LocationPickerSheet(title: title, items: items, selected: selected),
  );
}

class _LocationPickerSheet extends StatefulWidget {
  final String title;
  final List<String> items;
  final String? selected;

  const _LocationPickerSheet({required this.title, required this.items, required this.selected});

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
    return widget.items.where((item) => item.toLowerCase().contains(q)).toList(growable: false);
  }

  @override
  Widget build(BuildContext context) {
    final filtered = _filtered;
    return FractionallySizedBox(
      heightFactor: 0.86,
      child: Container(
        decoration: const BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.vertical(top: Radius.circular(26)),
        ),
        child: Column(
          children: [
            const SizedBox(height: 10),
            Container(
              width: 46,
              height: 5,
              decoration: BoxDecoration(color: const Color(0xFFD0D6DF), borderRadius: BorderRadius.circular(3)),
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(20, 14, 10, 12),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          'Choisir ${widget.title.toLowerCase()}',
                          style: const TextStyle(color: _ckNavy, fontSize: 25, fontWeight: FontWeight.w900),
                        ),
                        const SizedBox(height: 3),
                        Text(
                          '${widget.items.length} option${widget.items.length > 1 ? 's' : ''}',
                          style: const TextStyle(color: _ckMuted, fontSize: 15),
                        ),
                      ],
                    ),
                  ),
                  TextButton(
                    onPressed: () => Navigator.of(context).pop(),
                    child: const Text('Fermer', style: TextStyle(color: _ckBlue, fontSize: 16.5)),
                  ),
                ],
              ),
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(20, 0, 20, 8),
              child: TextField(
                controller: _search,
                onChanged: (value) => setState(() => _query = value),
                style: const TextStyle(color: _ckNavy, fontSize: 15.5),
                decoration: InputDecoration(
                  hintText: 'Rechercher...',
                  hintStyle: const TextStyle(color: Color(0xFF8A93A8), fontSize: 15.5),
                  prefixIcon: const Icon(Icons.search_rounded, color: _ckMuted, size: 26),
                  suffixIcon: _query.isEmpty
                      ? null
                      : IconButton(
                          onPressed: () {
                            _search.clear();
                            setState(() => _query = '');
                          },
                          icon: const Icon(Icons.close_rounded, size: 20),
                        ),
                  filled: true,
                  fillColor: _ckSoft,
                  contentPadding: const EdgeInsets.symmetric(vertical: 16),
                  border: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: BorderSide.none),
                ),
              ),
            ),
            Expanded(
              child: filtered.isEmpty
                  ? const Center(
                      child: Text(
                        'Aucun résultat',
                        style: TextStyle(color: _ckMuted, fontWeight: FontWeight.w600),
                      ),
                    )
                  : ListView.builder(
                      padding: const EdgeInsets.fromLTRB(20, 4, 20, 20),
                      itemCount: filtered.length,
                      itemBuilder: (context, index) {
                        final item = filtered[index];
                        final selected = widget.selected?.trim().toLowerCase() == item.toLowerCase();
                        return InkWell(
                          onTap: () => Navigator.of(context).pop(item),
                          borderRadius: BorderRadius.circular(10),
                          child: Container(
                            height: 64,
                            padding: const EdgeInsets.symmetric(horizontal: 16),
                            decoration: selected
                                ? BoxDecoration(
                                    color: const Color(0xFFFFF6F0),
                                    borderRadius: BorderRadius.circular(10),
                                    border: Border.all(color: OvanieColors.orange, width: 1.4),
                                  )
                                : const BoxDecoration(
                                    border: Border(bottom: BorderSide(color: _ckBorder)),
                                  ),
                            child: Row(
                              children: [
                                Expanded(
                                  child: Text(
                                    item,
                                    style: TextStyle(
                                      color: _ckNavy,
                                      fontSize: 17,
                                      fontWeight: selected ? FontWeight.w800 : FontWeight.w400,
                                    ),
                                  ),
                                ),
                                if (selected)
                                  Container(
                                    width: 30,
                                    height: 30,
                                    decoration: const BoxDecoration(color: OvanieColors.orange, shape: BoxShape.circle),
                                    child: const Icon(Icons.check_rounded, color: Colors.white, size: 20),
                                  ),
                              ],
                            ),
                          ),
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
        const Icon(Icons.error_outline_rounded, color: OvanieColors.orange, size: 20),
        const SizedBox(width: 9),
        Expanded(
          child: Text(message, style: const TextStyle(color: Color(0xFF8B3122), fontSize: 12)),
        ),
      ],
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

  const _AddressSheet({required this.initial, required this.savedAddresses, required this.loadingSavedAddresses});

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
  late final TextEditingController _landmark;
  late final TextEditingController _fullAddress;
  late final TextEditingController _otherQuartier;

  // « Ajouter une nouvelle adresse » : saisie manuelle commune / quartier.
  bool _manualMode = false;
  String _manualCommune = '';
  String _manualQuartier = '';
  String? _manualError;

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
    _phone = TextEditingController(text: formatCiPhoneDisplay(widget.initial.phone));
    _search = TextEditingController(text: widget.initial.savedAddressId == null ? widget.initial.address : '');
    _landmark = TextEditingController(text: widget.initial.notes);
    _fullAddress = TextEditingController();
    _otherQuartier = TextEditingController();
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
    _landmark.dispose();
    _fullAddress.dispose();
    _otherQuartier.dispose();
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
    final parts = clean.split(',').map((part) => part.trim()).where((part) => part.isNotEmpty).toList(growable: false);
    if (parts.length >= 3) {
      final first = parts.first.toLowerCase();
      const genericFirstParts = <String>{'abidjan', "côte d'ivoire", "cote d'ivoire"};
      return !genericFirstParts.contains(first);
    }

    return false;
  }

  bool get _hasSelectedLocation {
    final address = _address.trim();
    final commune = _commune.trim();
    final gpsSource = _geoSource == 'mobile_live_gps' || _geoSource == 'mobile_location_assist';

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

      if (_geoSource == 'mobile_live_gps' && accuracy > DeviceLocation.maxAcceptedAccuracyMeters) {
        return false;
      }
      if (_geoSource == 'mobile_location_assist' && accuracy > _addressLookupMaxAccuracyMeters) {
        return false;
      }

      return true;
    }

    final adminOk =
        (_zone == 'abidjan' && commune.isNotEmpty && commune.toLowerCase() != 'abidjan') ||
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

    final place = '${safeSelected.commune} ${safeSelected.city} ${safeSelected.address}'.toLowerCase();
    final zone = place.contains('abidjan') || (safeSelected.commune.isNotEmpty && safeSelected.city.isEmpty)
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
      _quartier = safeSelected.sousQuartier.isNotEmpty ? safeSelected.sousQuartier : safeSelected.quartier;
      _city = safeSelected.city;
      _address = safeSelected.address;
      _search.clear();
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
        lower.contains('abidjan') || firstLower == 'bingerville' || firstLower == 'anyama' || firstLower == 'songon';
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
    if (accuracy != null && accuracy.isFinite && accuracy > 0 && accuracy <= DeviceLocation.maxAcceptedAccuracyMeters) {
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
      final source = _sourceForLocationAccuracy(accuracy, emulator: position.isEmulator);

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
          (accuracy != null && accuracy.isFinite && accuracy > 0 && accuracy <= _addressLookupMaxAccuracyMeters)) {
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
      final permission = isLocationPermissionProblem(error.message);
      setState(() {
        _locationError = permission ? null : error.message;
        _geoStatus = '';
      });
      if (permission) {
        final retry = await showLocationPermissionDialog(context, error);
        if (retry && mounted) {
          WidgetsBinding.instance.addPostFrameCallback((_) {
            if (mounted) unawaited(_useCurrentPosition());
          });
        }
      }
    } on TimeoutException {
      if (!mounted) return;
      setState(() {
        _locationError = 'Nous n’avons pas pu détecter votre adresse. Saisissez-la manuellement.';
        _geoStatus = '';
      });
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _locationError = 'Nous n’avons pas pu détecter votre adresse. Saisissez-la manuellement.';
        _geoStatus = '';
      });
    } finally {
      if (mounted) setState(() => _locating = false);
    }
  }

  void _startLiveTracking() {
    _livePositionSubscription?.cancel();
    _livePositionSubscription = DeviceLocation.positionStream(distanceFilterMeters: 0).listen(
      (position) => unawaited(_applyLivePosition(position)),
      onError: (Object error) {
        if (!mounted || (_geoSource != 'mobile_live_gps' && _geoSource != 'mobile_location_assist')) {
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
    if (!mounted || (_geoSource != 'mobile_live_gps' && _geoSource != 'mobile_location_assist')) {
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
    final source = _sourceForLocationAccuracy(accuracy, emulator: position.isEmulator);

    final previousLat = _latitude;
    final previousLng = _longitude;
    final moved = previousLat == null || previousLng == null
        ? double.infinity
        : _distanceMeters(previousLat, previousLng, position.latitude, position.longitude);

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
          comparableAccuracy && moved >= math.max(12.0, math.min(40.0, previousAccuracy * 0.5));
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
    final sinceReverse = _lastReverseAt == null ? const Duration(days: 1) : now.difference(_lastReverseAt!);

    final improvedEnough = previousAccuracy == null || !previousAccuracy.isFinite || accuracy + 5 < previousAccuracy;

    if (!improvedEnough && moved < 10 && sinceReverse < const Duration(seconds: 6)) {
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
          (_geoSource != 'mobile_live_gps' && _geoSource != 'mobile_location_assist')) {
        return;
      }

      _applyResolvedPlace(place, source: source, exactLatitude: position.latitude, exactLongitude: position.longitude);
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
        math.cos(radians(lat1)) * math.cos(radians(lat2)) * math.sin(dLng / 2) * math.sin(dLng / 2);
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
      if (accuracy != null && accuracy.isFinite && accuracy > DeviceLocation.maxAcceptedAccuracyMeters) {
        // Si Android n'a pas un fix fin mais qu'une adresse a bien été
        // identifiée, reclasser la source en aide de localisation plutôt que
        // bloquer le checkout sur une métrique GPS invisible pour le client.
        _geoSource = 'mobile_location_assist';
      }
    }
    if (!_hasSelectedLocation) {
      final gpsAddressVisible =
          (_geoSource == 'mobile_live_gps' || _geoSource == 'mobile_location_assist') && _address.trim().isNotEmpty;
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

  static const String _otherQuartierOption = 'Autre quartier / zone';

  List<String> get _communeChoices {
    final loaded = OvanieReferenceDataStore.instance.communeNames;
    return loaded.isNotEmpty ? loaded : kAbidjanQuartiersByCommune.keys.toList(growable: false);
  }

  Map<String, List<String>> get _quarterChoices {
    final loaded = OvanieReferenceDataStore.instance.quartersByCommuneName;
    return loaded.isNotEmpty ? loaded : kAbidjanQuartiersByCommune;
  }

  bool get _gpsLocation => _geoSource == 'mobile_live_gps' || _geoSource == 'mobile_location_assist';

  void _chooseManualMode() {
    _stopLiveTracking();
    FocusManager.instance.primaryFocus?.unfocus();
    setState(() {
      _manualMode = true;
      _manualError = null;
      _locationError = null;
    });
  }

  Future<void> _pickCommune() async {
    FocusManager.instance.primaryFocus?.unfocus();
    final communes = _communeChoices;
    final selected = await showModalBottomSheet<String>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      backgroundColor: Colors.transparent,
      builder: (_) => _LocationPickerSheet(
        title: 'Commune',
        items: communes,
        selected: _manualCommune.isEmpty ? null : _manualCommune,
      ),
    );
    if (selected == null || !mounted) return;
    setState(() {
      if (selected != _manualCommune) _manualQuartier = '';
      _manualCommune = selected;
      _manualError = null;
    });
  }

  Future<void> _pickQuartier() async {
    if (_manualCommune.isEmpty) return;
    FocusManager.instance.primaryFocus?.unfocus();
    final quarters = [...(_quarterChoices[_manualCommune] ?? const <String>[]), _otherQuartierOption];
    final selected = await showModalBottomSheet<String>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      backgroundColor: Colors.transparent,
      builder: (_) => _LocationPickerSheet(
        title: 'Quartier',
        items: quarters,
        selected: _manualQuartier.isEmpty ? null : _manualQuartier,
      ),
    );
    if (selected == null || !mounted) return;
    setState(() {
      _manualQuartier = selected;
      _manualError = null;
    });
  }

  void _submitManual() {
    FocusManager.instance.primaryFocus?.unfocus();
    final name = _name.text.trim();
    final phone = _phone.text.trim();
    final commune = _manualCommune.trim();
    final quartier = _manualQuartier == _otherQuartierOption ? _otherQuartier.text.trim() : _manualQuartier.trim();
    final address = _fullAddress.text.trim();
    if (name.isEmpty || phone.isEmpty || commune.isEmpty || quartier.isEmpty || address.isEmpty) {
      setState(() {
        _manualError = 'Renseignez le destinataire, le téléphone, la commune, le quartier et l’adresse complète.';
      });
      return;
    }
    final inAbidjan = _communeChoices.any((item) => item.toLowerCase() == commune.toLowerCase());
    _stopLiveTracking();
    Navigator.of(context).pop(
      _AddressFormResult(
        name: name,
        phone: phone,
        zone: inAbidjan ? 'abidjan' : 'interieur',
        commune: commune,
        quartier: quartier,
        city: inAbidjan ? 'Abidjan' : commune,
        address: address,
        notes: _landmark.text.trim(),
        geoSource: 'mobile_manual',
      ),
    );
  }

  Widget _label(String text) => Padding(
    padding: const EdgeInsets.only(bottom: 8),
    child: Text(
      text,
      style: const TextStyle(color: _ckNavy, fontSize: 14.5, fontWeight: FontWeight.w500),
    ),
  );

  Widget _manualForm() {
    final otherQuartier = _manualQuartier == _otherQuartierOption;
    return Container(
      padding: const EdgeInsets.fromLTRB(16, 18, 16, 16),
      decoration: BoxDecoration(color: const Color(0xFFF4F6FA), borderRadius: BorderRadius.circular(14)),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text(
            'Ajouter une nouvelle adresse',
            style: TextStyle(color: _ckNavy, fontSize: 19, fontWeight: FontWeight.w800),
          ),
          const SizedBox(height: 4),
          const Text(
            'Rechercher, utiliser ma position ou saisir l’adresse',
            style: TextStyle(color: _ckMuted, fontSize: 13.5),
          ),
          const SizedBox(height: 16),
          _label('Nom du destinataire'),
          TextField(
            controller: _name,
            textInputAction: TextInputAction.next,
            style: const TextStyle(color: _ckNavy, fontSize: 15),
            decoration: _ckInputDecoration(),
          ),
          const SizedBox(height: 14),
          _label('Téléphone'),
          TextField(
            controller: _phone,
            keyboardType: TextInputType.phone,
            inputFormatters: const [CiPhoneInputFormatter(withCountryCode: true)],
            style: const TextStyle(color: _ckNavy, fontSize: 15),
            decoration: _ckInputDecoration(),
          ),
          const SizedBox(height: 14),
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    _label('Commune'),
                    _PickerField(value: _manualCommune, hint: 'Choisir', enabled: true, onTap: _pickCommune),
                  ],
                ),
              ),
              const SizedBox(width: 14),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    _label('Quartier'),
                    _PickerField(
                      value: _manualQuartier,
                      hint: _manualCommune.isEmpty ? 'Commune d’abord' : 'Choisir',
                      enabled: _manualCommune.isNotEmpty,
                      onTap: _pickQuartier,
                    ),
                  ],
                ),
              ),
            ],
          ),
          if (otherQuartier) ...[
            const SizedBox(height: 14),
            _label('Quartier / zone'),
            TextField(
              controller: _otherQuartier,
              style: const TextStyle(color: _ckNavy, fontSize: 15),
              decoration: _ckInputDecoration(hint: 'Saisir le quartier ou la zone'),
            ),
          ],
          const SizedBox(height: 14),
          _label('Repère / point de repère'),
          TextField(
            controller: _landmark,
            style: const TextStyle(color: _ckNavy, fontSize: 15),
            decoration: _ckInputDecoration(hint: 'Ex. Immeuble Orange Money, près du carrefour'),
          ),
          const SizedBox(height: 14),
          _label('Adresse complète'),
          TextField(
            controller: _fullAddress,
            style: const TextStyle(color: _ckNavy, fontSize: 15),
            decoration: _ckInputDecoration(hint: 'Rue, quartier, commune, ville'),
          ),
          const SizedBox(height: 10),
          const Text(
            'Les frais de livraison seront calculés après confirmation de l’adresse.',
            style: TextStyle(color: _ckMuted, fontSize: 13),
          ),
          if (_manualError != null) ...[
            const SizedBox(height: 10),
            Text(
              _manualError!,
              style: const TextStyle(color: _ckRed, fontSize: 13, fontWeight: FontWeight.w600),
            ),
          ],
        ],
      ),
    );
  }

  List<Widget> _placeAndContact() {
    final showPlaceCard = _hasSelectedLocation && _savedAddressId == null;
    return [
      const _SheetHeading(
        title: 'Lieu de livraison',
        subtitle: 'Recherchez un quartier, une rue, une résidence ou un lieu connu.',
      ),
      const SizedBox(height: 12),
      if (!showPlaceCard) _label('Adresse ou lieu'),
      TextField(
        controller: _search,
        onChanged: _onSearchChanged,
        textInputAction: TextInputAction.search,
        style: const TextStyle(color: _ckNavy, fontSize: 15),
        decoration: _ckInputDecoration(hint: 'Ex. Riviera 3, Angré, Zone 4, Bouaké...'),
      ),
      if (_searching || _geoStatus.trim().isNotEmpty) ...[
        const SizedBox(height: 8),
        Row(
          children: [
            if (_searching) ...[
              const SizedBox(width: 13, height: 13, child: CircularProgressIndicator(strokeWidth: 1.8)),
              const SizedBox(width: 8),
            ],
            Expanded(
              child: Text(
                _searching ? 'Recherche de l’adresse…' : _geoStatus,
                style: const TextStyle(color: _ckMuted, fontSize: 12.5),
              ),
            ),
          ],
        ),
      ],
      if (_searchResults.isNotEmpty) ...[
        const SizedBox(height: 10),
        Container(
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(12),
            border: Border.all(color: _ckBorder),
          ),
          child: Column(
            children: [
              for (var index = 0; index < _searchResults.length; index++) ...[
                _AddressResultTile(
                  result: _searchResults[index],
                  onTap: () => _selectSearchResult(_searchResults[index]),
                ),
                if (index != _searchResults.length - 1) const Divider(height: 1),
              ],
            ],
          ),
        ),
      ],
      const SizedBox(height: 14),
      if (showPlaceCard)
        _DetectedPlaceCard(
          title: _gpsLocation ? 'Adresse détectée' : 'Adresse sélectionnée',
          place: _commune.trim().isNotEmpty
              ? _commune.trim()
              : (_quartier.trim().isNotEmpty ? _quartier.trim() : _city),
          detail: _gpsLocation ? _formatAccuracy(_locationAccuracyMeters) : _address,
          onEdit: _editSelectedLocation,
        )
      else
        SizedBox(
          width: double.infinity,
          height: 54,
          child: OutlinedButton(
            onPressed: _locating ? null : _useCurrentPosition,
            style: OutlinedButton.styleFrom(
              foregroundColor: _ckBlue,
              side: const BorderSide(color: _ckBlue, width: 1.4),
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
            ),
            child: _locating
                ? const SizedBox(
                    width: 22,
                    height: 22,
                    child: CircularProgressIndicator(strokeWidth: 2.2, color: _ckBlue),
                  )
                : const Row(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Icon(Icons.my_location_rounded, size: 26),
                      SizedBox(width: 12),
                      Text('Ma position', style: TextStyle(fontSize: 17, fontWeight: FontWeight.w700)),
                    ],
                  ),
          ),
        ),
      if (_locationError != null) ...[
        const SizedBox(height: 12),
        _LocationWarningBox(message: _locationError!, onRetry: _useCurrentPosition),
      ],
      const SizedBox(height: 26),
      const _SheetHeading(
        title: 'Contact de livraison',
        subtitle: 'Ces coordonnées servent au suivi et à la remise de la commande.',
      ),
      const SizedBox(height: 14),
      _label('Nom complet'),
      TextFormField(
        controller: _name,
        textInputAction: TextInputAction.next,
        style: const TextStyle(color: _ckNavy, fontSize: 15),
        decoration: _ckInputDecoration(),
        validator: (value) => _required(value, 'Renseignez le nom complet.'),
      ),
      const SizedBox(height: 14),
      _label('Numéro WhatsApp'),
      TextFormField(
        controller: _phone,
        keyboardType: TextInputType.phone,
        inputFormatters: const [CiPhoneInputFormatter(withCountryCode: true)],
        style: const TextStyle(color: _ckNavy, fontSize: 15),
        decoration: _ckInputDecoration(),
        validator: (value) => _required(value, 'Renseignez le numéro WhatsApp.'),
      ),
    ];
  }

  @override
  Widget build(BuildContext context) {
    final keyboard = MediaQuery.viewInsetsOf(context).bottom;
    final bottomInset = MediaQuery.paddingOf(context).bottom;

    return FractionallySizedBox(
      heightFactor: 0.9,
      child: Container(
        decoration: const BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.vertical(top: Radius.circular(26)),
        ),
        child: Column(
          children: [
            const SizedBox(height: 10),
            Container(
              width: 46,
              height: 5,
              decoration: BoxDecoration(color: const Color(0xFFD0D6DF), borderRadius: BorderRadius.circular(3)),
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(20, 14, 10, 4),
              child: Row(
                children: [
                  const Expanded(
                    child: Text(
                      'Adresse de livraison',
                      style: TextStyle(color: _ckNavy, fontSize: 25, fontWeight: FontWeight.w900),
                    ),
                  ),
                  TextButton(
                    onPressed: () => Navigator.of(context).pop(),
                    child: const Text('Fermer', style: TextStyle(color: _ckBlue, fontSize: 16.5)),
                  ),
                ],
              ),
            ),
            Expanded(
              child: Form(
                key: _formKey,
                child: ListView(
                  padding: EdgeInsets.fromLTRB(20, 6, 20, 20),
                  children: [
                    if (widget.loadingSavedAddresses)
                      const Padding(
                        padding: EdgeInsets.only(bottom: 12),
                        child: LinearProgressIndicator(minHeight: 2, color: OvanieColors.orange),
                      ),
                    const _SheetHeading(
                      title: 'Mes adresses enregistrées',
                      subtitle: 'Choisissez une adresse de votre carnet ou recherchez une nouvelle adresse.',
                    ),
                    const SizedBox(height: 12),
                    for (final item in widget.savedAddresses)
                      Padding(
                        padding: const EdgeInsets.only(bottom: 12),
                        child: _SavedAddressChoice(
                          title:
                              '${item.label.trim().isEmpty ? 'Adresse' : item.label.trim()} — '
                              '${item.commune.isNotEmpty ? item.commune : item.city}',
                          selected: !_manualMode && item.id == _savedAddressId,
                          onTap: () {
                            setState(() => _manualMode = false);
                            _applySavedAddress(item.id);
                          },
                        ),
                      ),
                    _SavedAddressChoice(
                      title: 'Ajouter une nouvelle adresse',
                      selected: _manualMode,
                      showSavedChip: false,
                      onTap: _chooseManualMode,
                    ),
                    const SizedBox(height: 18),
                    if (_manualMode) _manualForm() else ..._placeAndContact(),
                  ],
                ),
              ),
            ),
            Padding(
              padding: EdgeInsets.fromLTRB(20, 10, 20, 8 + (keyboard > 0 ? keyboard : bottomInset)),
              child: _manualMode
                  ? Row(
                      children: [
                        Expanded(
                          child: SizedBox(
                            height: 56,
                            child: OutlinedButton(
                              onPressed: () => Navigator.of(context).pop(),
                              style: OutlinedButton.styleFrom(
                                foregroundColor: _ckBlue,
                                side: const BorderSide(color: Color(0xFFCBD3DF)),
                                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                              ),
                              child: const Text('Annuler', style: TextStyle(fontSize: 17, fontWeight: FontWeight.w500)),
                            ),
                          ),
                        ),
                        const SizedBox(width: 14),
                        Expanded(
                          child: SizedBox(
                            height: 56,
                            child: FilledButton(
                              onPressed: _submitManual,
                              style: FilledButton.styleFrom(
                                elevation: 0,
                                backgroundColor: OvanieColors.orange,
                                foregroundColor: Colors.white,
                                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                              ),
                              child: const Text(
                                'Enregistrer',
                                style: TextStyle(fontSize: 17, fontWeight: FontWeight.w700),
                              ),
                            ),
                          ),
                        ),
                      ],
                    )
                  : Column(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        SizedBox(
                          width: double.infinity,
                          height: 56,
                          child: FilledButton(
                            onPressed: _submit,
                            style: FilledButton.styleFrom(
                              elevation: 0,
                              backgroundColor: OvanieColors.orange,
                              foregroundColor: Colors.white,
                              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                            ),
                            child: const Text(
                              'Confirmer cette adresse',
                              style: TextStyle(fontSize: 17, fontWeight: FontWeight.w700),
                            ),
                          ),
                        ),
                        TextButton(
                          onPressed: () => Navigator.of(context).pop(),
                          child: const Text('Annuler', style: TextStyle(color: _ckBlue, fontSize: 16.5)),
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

class _SheetHeading extends StatelessWidget {
  final String title;
  final String subtitle;

  const _SheetHeading({required this.title, required this.subtitle});

  @override
  Widget build(BuildContext context) => Column(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: [
      Text(
        title,
        style: const TextStyle(color: _ckNavy, fontSize: 19, fontWeight: FontWeight.w800),
      ),
      const SizedBox(height: 3),
      Text(subtitle, style: const TextStyle(color: _ckMuted, fontSize: 13.5, height: 1.35)),
    ],
  );
}

class _SavedAddressChoice extends StatelessWidget {
  final String title;
  final bool selected;
  final bool showSavedChip;
  final VoidCallback onTap;

  const _SavedAddressChoice({
    required this.title,
    required this.selected,
    required this.onTap,
    this.showSavedChip = true,
  });

  @override
  Widget build(BuildContext context) => InkWell(
    onTap: onTap,
    borderRadius: BorderRadius.circular(12),
    child: Container(
      constraints: const BoxConstraints(minHeight: 66),
      padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 12),
      decoration: _choiceDecoration(selected),
      child: Row(
        children: [
          _RadioDot(selected: selected),
          const SizedBox(width: 18),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: const TextStyle(color: _ckNavy, fontSize: 16, fontWeight: FontWeight.w700),
                ),
                if (selected && showSavedChip) ...[
                  const SizedBox(height: 6),
                  const _GreyChip(label: 'Adresse enregistrée'),
                ],
              ],
            ),
          ),
        ],
      ),
    ),
  );
}

class _DetectedPlaceCard extends StatelessWidget {
  final String title;
  final String place;
  final String detail;
  final VoidCallback onEdit;

  const _DetectedPlaceCard({required this.title, required this.place, required this.detail, required this.onEdit});

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.fromLTRB(14, 14, 10, 14),
    decoration: BoxDecoration(
      color: const Color(0xFFEDF8F0),
      borderRadius: BorderRadius.circular(12),
      border: Border.all(color: const Color(0xFFBFE5CB)),
    ),
    child: Row(
      children: [
        Container(
          width: 52,
          height: 52,
          decoration: const BoxDecoration(color: Color(0xFFD5F0DD), shape: BoxShape.circle),
          child: const Icon(Icons.location_on_rounded, color: Color(0xFF15803D), size: 30),
        ),
        const SizedBox(width: 14),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                place.trim().isEmpty ? title : '$title · ${place.trim()}',
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(color: _ckNavy, fontSize: 16, fontWeight: FontWeight.w700),
              ),
              if (detail.trim().isNotEmpty) ...[
                const SizedBox(height: 3),
                Text(
                  detail.trim(),
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(color: _ckMuted, fontSize: 13.5),
                ),
              ],
            ],
          ),
        ),
        TextButton(
          onPressed: onEdit,
          child: const Text('Modifier', style: TextStyle(color: _ckBlue, fontSize: 15.5)),
        ),
      ],
    ),
  );
}

class _PickerField extends StatelessWidget {
  final String value;
  final String hint;
  final bool enabled;
  final VoidCallback onTap;

  const _PickerField({required this.value, required this.hint, required this.enabled, required this.onTap});

  @override
  Widget build(BuildContext context) => InkWell(
    onTap: enabled ? onTap : null,
    borderRadius: BorderRadius.circular(10),
    child: Container(
      height: 52,
      padding: const EdgeInsets.symmetric(horizontal: 14),
      decoration: BoxDecoration(
        color: enabled ? Colors.white : const Color(0xFFE9EDF3),
        borderRadius: BorderRadius.circular(10),
        border: Border.all(color: enabled ? const Color(0xFFD5DCE6) : const Color(0xFFE1E6EE)),
      ),
      child: Row(
        children: [
          Expanded(
            child: Text(
              value.isEmpty ? hint : value,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: TextStyle(
                color: value.isEmpty ? (enabled ? _ckNavy : const Color(0xFF8A93A8)) : _ckNavy,
                fontSize: 15,
              ),
            ),
          ),
          if (enabled) const Icon(Icons.keyboard_arrow_down_rounded, color: _ckNavy, size: 24),
        ],
      ),
    ),
  );
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
            const Icon(Icons.location_on_outlined, size: 18, color: OvanieColors.orange),
            const SizedBox(width: 8),
            Expanded(
              child: Text(
                result.displayName,
                style: const TextStyle(
                  color: OvanieColors.text,
                  fontSize: 12,
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
        style: const TextStyle(color: Color(0xFFB42318), fontSize: 11.5, height: 1.35, fontWeight: FontWeight.w700),
      ),
    );
  }
}

class _MobileOnlinePaymentScreen extends StatefulWidget {
  final CheckoutOrderResult order;
  final String initialPhone;
  final List<CheckoutPaymentOperatorOption> operators;

  const _MobileOnlinePaymentScreen({required this.order, required this.initialPhone, this.operators = const []});

  @override
  State<_MobileOnlinePaymentScreen> createState() => _MobileOnlinePaymentScreenState();
}

class _MobileOnlinePaymentScreenState extends State<_MobileOnlinePaymentScreen> with WidgetsBindingObserver {
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

  static const _operatorVisuals = <String, ({String asset, String placeholder})>{
    'wave': (asset: 'assets/images/operators/wave.png', placeholder: '07 00 00 00 00'),
    'orange': (asset: 'assets/images/operators/orange.png', placeholder: '07 00 00 00 00'),
    'mtn': (asset: 'assets/images/operators/mtn.png', placeholder: '05 00 00 00 00'),
    'moov': (asset: 'assets/images/operators/moov.png', placeholder: '01 00 00 00 00'),
  };

  List<({String code, String label, String asset, String placeholder})> get _mobileOperators {
    final source = widget.operators
        .where((item) => item.code != 'card' && _operatorVisuals.containsKey(item.code))
        .map((item) {
          final visual = _operatorVisuals[item.code]!;
          return (code: item.code, label: item.label, asset: visual.asset, placeholder: visual.placeholder);
        })
        .toList(growable: false);

    return source;
  }

  bool get _cardEnabled => widget.operators.any((item) => item.code == 'card');

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _phone = TextEditingController(text: formatCiPhoneDisplay(widget.initialPhone, includeCountryCode: false));
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
    if (state == AppLifecycleState.resumed && _paymentOpened && !_paymentCompleted) {
      unawaited(_checkPaymentStatus());
    }
  }

  ({String code, String label, String asset, String placeholder})? get _selected {
    if (_mobileOperators.isEmpty) return null;
    return _mobileOperators.firstWhere((item) => item.code == _operator, orElse: () => _mobileOperators.first);
  }

  Future<void> _pay({String? forceOperator}) async {
    if (_submitting) return;
    final operator = forceOperator ?? _operator;
    if (operator.isEmpty) {
      setState(() {
        _error = 'Les opérateurs Mobile Money OVANIE sont indisponibles. Actualisez puis réessayez.';
      });
      return;
    }
    final isCard = operator == 'card';
    final localPhone = ciLocalPhoneDigits(_phone.text);
    final phone = localPhone.length == 10 ? '+225$localPhone' : _phone.text.trim();

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
      final order = await const OrdersRepository().fetchOrder(widget.order.orderId);
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
            Text('Paiement sécurisé', style: TextStyle(fontWeight: FontWeight.w900)),
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
                boxShadow: const [BoxShadow(color: Color(0x0D071B48), blurRadius: 20, offset: Offset(0, 8))],
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Text(
                    'Paiement Mobile Money',
                    textAlign: TextAlign.center,
                    style: TextStyle(color: OvanieColors.navy, fontSize: 24, fontWeight: FontWeight.w900),
                  ),
                  const SizedBox(height: 7),
                  const Text(
                    'Choisissez votre opérateur et saisissez le numéro à débiter.',
                    textAlign: TextAlign.center,
                    style: TextStyle(color: OvanieColors.muted, fontSize: 12.5, height: 1.4),
                  ),
                  const SizedBox(height: 20),
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 14),
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
                              style: TextStyle(color: OvanieColors.muted, fontSize: 11.5, fontWeight: FontWeight.w700),
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
                    style: TextStyle(color: OvanieColors.text, fontSize: 12.5, fontWeight: FontWeight.w900),
                  ),
                  const SizedBox(height: 7),
                  DropdownButtonFormField<String>(
                    value: _operator.isEmpty ? null : _operator,
                    isExpanded: true,
                    decoration: const InputDecoration(
                      contentPadding: EdgeInsets.symmetric(horizontal: 12, vertical: 14),
                    ),
                    selectedItemBuilder: (context) => _mobileOperators
                        .map(
                          (item) => Row(
                            children: [
                              SizedBox(width: 34, height: 28, child: Image.asset(item.asset, fit: BoxFit.contain)),
                              const SizedBox(width: 10),
                              Text(
                                item.label,
                                style: const TextStyle(color: OvanieColors.text, fontWeight: FontWeight.w900),
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
                                SizedBox(width: 34, height: 28, child: Image.asset(item.asset, fit: BoxFit.contain)),
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
                    style: TextStyle(color: OvanieColors.text, fontSize: 12.5, fontWeight: FontWeight.w900),
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
                  if (_error != null) ...[const SizedBox(height: 14), _ErrorBox(message: _error!)],
                  const SizedBox(height: 18),
                  SizedBox(
                    height: 54,
                    child: FilledButton(
                      onPressed: _submitting || _operator.isEmpty ? null : () => _pay(),
                      style: FilledButton.styleFrom(
                        backgroundColor: OvanieColors.orange,
                        foregroundColor: Colors.white,
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(13)),
                      ),
                      child: _submitting
                          ? const SizedBox(
                              width: 22,
                              height: 22,
                              child: CircularProgressIndicator(strokeWidth: 2.3, color: Colors.white),
                            )
                          : Row(
                              children: [
                                const Text('Payer maintenant', style: TextStyle(fontWeight: FontWeight.w900)),
                                const Spacer(),
                                Text(formatFcfa(amount), style: const TextStyle(fontWeight: FontWeight.w900)),
                              ],
                            ),
                    ),
                  ),
                  const SizedBox(height: 13),
                  const Row(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Icon(Icons.verified_user_outlined, size: 17, color: Color(0xFF159455)),
                      SizedBox(width: 7),
                      Flexible(
                        child: Text(
                          'Transaction sécurisée. Ne communiquez jamais votre code secret.',
                          textAlign: TextAlign.center,
                          style: TextStyle(color: OvanieColors.muted, fontSize: 12),
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
                    style: TextStyle(color: OvanieColors.muted, fontSize: 11, fontWeight: FontWeight.w700),
                  ),
                  const SizedBox(height: 10),
                  Row(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: _mobileOperators
                        .map(
                          (item) => Padding(
                            padding: const EdgeInsets.symmetric(horizontal: 7),
                            child: SizedBox(width: 38, height: 28, child: Image.asset(item.asset, fit: BoxFit.contain)),
                          ),
                        )
                        .toList(growable: false),
                  ),
                  if (_cardEnabled) ...[
                    const SizedBox(height: 16),
                    OutlinedButton.icon(
                      onPressed: _submitting ? null : () => _pay(forceOperator: 'card'),
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

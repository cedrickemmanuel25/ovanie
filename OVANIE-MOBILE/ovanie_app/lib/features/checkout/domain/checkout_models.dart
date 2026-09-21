import '../../../core/utils/text_cleaner.dart';

class CheckoutAddress {
  final String fullName;
  final String phone;
  final String zone;
  final String commune;
  final String quartier;
  final String city;
  final String address;
  final String paymentMethod;
  final String notes;
  final double? latitude;
  final double? longitude;
  final double? geoAccuracyMeters;
  final int? savedAddressId;
  final String geoSource;
  final String localityType;
  final String onlineOperator;
  final String paymentPhone;
  final String orangeOtp;
  final List<int> checkoutProductIds;

  const CheckoutAddress({
    required this.fullName,
    required this.phone,
    required this.zone,
    required this.commune,
    required this.quartier,
    required this.city,
    required this.address,
    required this.paymentMethod,
    this.notes = '',
    this.latitude,
    this.longitude,
    this.geoAccuracyMeters,
    this.savedAddressId,
    this.geoSource = 'mobile_manual',
    this.localityType = '',
    this.onlineOperator = '',
    this.paymentPhone = '',
    this.orangeOtp = '',
    this.checkoutProductIds = const <int>[],
  });

  Map<String, dynamic> toJson() => {
        'full_name': fullName.trim(),
        'phone': phone.trim(),
        'delivery_destination_type': 'home',
        'delivery_zone': zone,
        'delivery_city': zone == 'interieur' ? city.trim() : null,
        'delivery_commune': commune.trim().isEmpty ? null : commune.trim(),
        'delivery_quartier': quartier.trim().isEmpty ? null : quartier.trim(),
        'address': address.trim(),
        'delivery_latitude': latitude,
        'delivery_longitude': longitude,
        'delivery_geo_accuracy': geoAccuracyMeters,
        'delivery_geo_source': geoSource.trim().isEmpty
            ? (latitude != null && longitude != null ? 'mobile_gps' : 'mobile_manual')
            : geoSource.trim(),
        'delivery_locality_type': localityType.trim().isEmpty ? null : localityType.trim(),
        'saved_address_id': savedAddressId,
        'payment_method': paymentMethod,
        'online_operator': onlineOperator.trim().isEmpty ? null : onlineOperator.trim(),
        'payment_phone': paymentPhone.trim().isEmpty ? null : paymentPhone.trim(),
        'orange_otp': orangeOtp.trim().isEmpty ? null : orangeOtp.trim(),
        if (checkoutProductIds.isNotEmpty) 'checkout_product_ids': checkoutProductIds,
        'loyalty_points': 0,
        'notes': notes.trim().isEmpty ? null : notes.trim(),
      };
}

class CheckoutSavedAddress {
  final int id;
  final String label;
  final String recipientName;
  final String phone;
  final String city;
  final String commune;
  final String quartier;
  final String quartierPrincipal;
  final String sousQuartier;
  final String localityType;
  final String localityTypeLabel;
  final String address;
  final double? latitude;
  final double? longitude;
  final bool isDefault;

  const CheckoutSavedAddress({
    required this.id,
    required this.label,
    required this.recipientName,
    required this.phone,
    required this.city,
    required this.commune,
    required this.quartier,
    required this.quartierPrincipal,
    required this.sousQuartier,
    required this.localityType,
    required this.localityTypeLabel,
    required this.address,
    required this.latitude,
    required this.longitude,
    required this.isDefault,
  });

  factory CheckoutSavedAddress.fromJson(Map<String, dynamic> json) {
    double? asDouble(dynamic value) {
      if (value == null) return null;
      if (value is num) return value.toDouble();
      return double.tryParse(value.toString());
    }

    return CheckoutSavedAddress(
      id: int.tryParse('${json['id'] ?? ''}') ?? 0,
      label: cleanOvanieText((json['label'] ?? 'Adresse').toString()),
      recipientName: cleanOvanieText((json['recipient_name'] ?? '').toString()),
      phone: (json['phone'] ?? '').toString().trim(),
      city: cleanOvanieText((json['city'] ?? '').toString()),
      commune: cleanOvanieText((json['commune'] ?? '').toString()),
      quartier: cleanOvanieText((json['quartier'] ?? '').toString()),
      quartierPrincipal: cleanOvanieText((json['quartier_principal'] ?? '').toString()),
      sousQuartier: cleanOvanieText((json['sous_quartier'] ?? '').toString()),
      localityType: cleanOvanieText((json['locality_type'] ?? '').toString()),
      localityTypeLabel: cleanOvanieText((json['locality_type_label'] ?? '').toString()),
      address: cleanOvanieText((json['address'] ?? '').toString()),
      latitude: asDouble(json['latitude']),
      longitude: asDouble(json['longitude']),
      isDefault: json['is_default'] == true || '${json['is_default']}' == '1',
    );
  }
}

class CheckoutPreview {
  final double subtotal;
  final double deliveryFee;
  final double total;
  final double commission;
  final bool deliveryAvailable;
  final bool deliveryCalculated;
  final bool deliveryQuoteRequired;
  final String deliveryMessage;
  final double totalWeightKg;
  final double totalVolumeM3;
  final String recommendedVehicle;
  final String deliveryZone;
  final String deliveryCommune;
  final String deliveryQuartier;
  final String deliveryLocalityType;
  final String deliveryCity;
  final String deliveryAddress;
  final CheckoutPaymentOptions paymentOptions;
  final CheckoutValidationState validationState;

  const CheckoutPreview({
    required this.subtotal,
    required this.deliveryFee,
    required this.total,
    required this.commission,
    required this.deliveryAvailable,
    required this.deliveryCalculated,
    required this.deliveryQuoteRequired,
    required this.deliveryMessage,
    required this.totalWeightKg,
    required this.totalVolumeM3,
    required this.recommendedVehicle,
    required this.deliveryZone,
    required this.deliveryCommune,
    required this.deliveryQuartier,
    required this.deliveryLocalityType,
    required this.deliveryCity,
    required this.deliveryAddress,
    required this.paymentOptions,
    required this.validationState,
  });

  factory CheckoutPreview.fromJson(Map<String, dynamic> json) {
    double asDouble(dynamic value) {
      if (value is num) return value.toDouble();
      return double.tryParse('${value ?? ''}') ?? 0;
    }

    final resolved = json['resolved_delivery_location'] is Map
        ? Map<String, dynamic>.from(json['resolved_delivery_location'] as Map)
        : <String, dynamic>{};

    String text(dynamic value) => cleanOvanieText((value ?? '').toString());

    return CheckoutPreview(
      subtotal: asDouble(json['subtotal']),
      deliveryFee: asDouble(json['delivery_fee']),
      total: asDouble(json['total']),
      commission: asDouble(json['commission']),
      deliveryAvailable: json['delivery_available'] == true,
      deliveryCalculated: json['delivery_calculated'] == true,
      deliveryQuoteRequired: json['delivery_quote_required'] == true,
      deliveryMessage: text(json['delivery_message']),
      totalWeightKg: asDouble(json['total_weight_kg']),
      totalVolumeM3: asDouble(json['total_volume_m3']),
      recommendedVehicle: text(json['recommended_vehicle']),
      deliveryZone: text(resolved['zone']),
      deliveryCommune: text(resolved['commune']),
      deliveryQuartier: text(resolved['quartier']),
      deliveryLocalityType: text(resolved['locality_type']),
      deliveryCity: text(resolved['city']),
      deliveryAddress: text(resolved['address']),
      paymentOptions: CheckoutPaymentOptions.fromJson(
        json['payment_options'] is Map
            ? Map<String, dynamic>.from(json['payment_options'] as Map)
            : const <String, dynamic>{},
      ),
      validationState: CheckoutValidationState.fromJson(
        json['checkout_state'] is Map
            ? Map<String, dynamic>.from(json['checkout_state'] as Map)
            : const <String, dynamic>{},
      ),
    );
  }
}


class CheckoutValidationState {
  final bool cartValid;
  final bool addressValid;
  final bool contactValid;
  final bool deliveryValid;
  final bool paymentMethodValid;
  final bool canPlaceOrder;
  final String nextStep;

  const CheckoutValidationState({
    required this.cartValid,
    required this.addressValid,
    required this.contactValid,
    required this.deliveryValid,
    required this.paymentMethodValid,
    required this.canPlaceOrder,
    required this.nextStep,
  });

  factory CheckoutValidationState.fromJson(Map<String, dynamic> json) {
    return CheckoutValidationState(
      cartValid: json['cart_valid'] == true,
      addressValid: json['address_valid'] == true,
      contactValid: json['contact_valid'] == true,
      deliveryValid: json['delivery_valid'] == true,
      paymentMethodValid: json['payment_method_valid'] == true,
      canPlaceOrder: json['can_place_order'] == true,
      nextStep: (json['next_step'] ?? 'delivery').toString(),
    );
  }
}

class CheckoutPaymentMethodOption {
  final String code;
  final String label;
  final String description;
  final bool enabled;
  final bool required;
  final String reason;
  final List<CheckoutPaymentOperatorOption> operators;

  const CheckoutPaymentMethodOption({
    required this.code,
    required this.label,
    required this.description,
    required this.enabled,
    required this.required,
    required this.reason,
    required this.operators,
  });

  factory CheckoutPaymentMethodOption.fromJson(Map<String, dynamic> json) {
    final rawOperators = json['operators'];
    return CheckoutPaymentMethodOption(
      code: (json['code'] ?? '').toString(),
      label: cleanOvanieText((json['label'] ?? '').toString()),
      description: cleanOvanieText((json['description'] ?? '').toString()),
      enabled: json['enabled'] == true,
      required: json['required'] == true,
      reason: cleanOvanieText((json['reason'] ?? '').toString()),
      operators: rawOperators is List
          ? rawOperators
              .whereType<Map>()
              .map((item) => CheckoutPaymentOperatorOption.fromJson(
                    Map<String, dynamic>.from(item),
                  ))
              .toList(growable: false)
          : const [],
    );
  }
}

class CheckoutPaymentOperatorOption {
  final String code;
  final String label;

  const CheckoutPaymentOperatorOption({required this.code, required this.label});

  factory CheckoutPaymentOperatorOption.fromJson(Map<String, dynamic> json) {
    return CheckoutPaymentOperatorOption(
      code: (json['code'] ?? '').toString(),
      label: cleanOvanieText((json['label'] ?? '').toString()),
    );
  }
}

class CheckoutPaymentOptions {
  final bool cashOnDeliveryRequired;
  final double onlineLimitXof;
  final List<CheckoutPaymentMethodOption> methods;

  const CheckoutPaymentOptions({
    required this.cashOnDeliveryRequired,
    required this.onlineLimitXof,
    required this.methods,
  });

  factory CheckoutPaymentOptions.fromJson(Map<String, dynamic> json) {
    final raw = json['methods'];
    final limit = json['online_limit_xof'];
    return CheckoutPaymentOptions(
      cashOnDeliveryRequired: json['cash_on_delivery_required'] == true,
      onlineLimitXof: limit is num
          ? limit.toDouble()
          : double.tryParse('${limit ?? ''}') ?? 3000000,
      methods: raw is List
          ? raw
              .whereType<Map>()
              .map((item) => CheckoutPaymentMethodOption.fromJson(
                    Map<String, dynamic>.from(item),
                  ))
              .toList(growable: false)
          : const [],
    );
  }

  CheckoutPaymentMethodOption? byCode(String code) {
    for (final item in methods) {
      if (item.code == code) return item;
    }
    return null;
  }
}

class CheckoutOrderResult {
  final int orderId;
  final String orderNumber;
  final String paymentStatus;
  final String paymentMethod;
  final double paymentAmount;
  final String paymentUrl;
  final String paymentOperator;

  const CheckoutOrderResult({
    required this.orderId,
    required this.orderNumber,
    required this.paymentStatus,
    required this.paymentMethod,
    required this.paymentAmount,
    required this.paymentUrl,
    required this.paymentOperator,
  });
}

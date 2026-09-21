import '../../../core/utils/text_cleaner.dart';

int _toInt(dynamic value) => value is num ? value.toInt() : int.tryParse('${value ?? ''}') ?? 0;
double _toDouble(dynamic value) => value is num ? value.toDouble() : double.tryParse('${value ?? ''}') ?? 0;
DateTime? _toDate(dynamic value) {
  final raw = value?.toString().trim() ?? '';
  return raw.isEmpty ? null : DateTime.tryParse(raw)?.toLocal();
}

class ClientProfile {
  final int id;
  final String firstName;
  final String lastName;
  final String name;
  final String email;
  final String phone;
  final String whatsappPhone;
  final String status;

  const ClientProfile({
    required this.id,
    required this.firstName,
    required this.lastName,
    required this.name,
    required this.email,
    required this.phone,
    required this.whatsappPhone,
    required this.status,
  });

  factory ClientProfile.fromJson(Map<String, dynamic> json) => ClientProfile(
        id: _toInt(json['id']),
        firstName: cleanOvanieText('${json['first_name'] ?? ''}'),
        lastName: cleanOvanieText('${json['last_name'] ?? ''}'),
        name: cleanOvanieText('${json['name'] ?? ''}'),
        email: '${json['email'] ?? ''}'.trim(),
        phone: '${json['phone'] ?? ''}'.trim(),
        whatsappPhone: '${json['whatsapp_phone'] ?? ''}'.trim(),
        status: '${json['status'] ?? ''}'.trim(),
      );

  Map<String, dynamic> toSessionJson() => {
        'id': id,
        'first_name': firstName,
        'last_name': lastName,
        'name': name,
        'email': email,
        'phone': phone,
        'whatsapp_phone': whatsappPhone,
        'role': 'client',
        'status': status,
      };
}

class AccountCapabilities {
  final bool paymentMethods;
  final bool loyalty;
  final bool giftCards;
  final bool recentlyViewedSync;
  final bool supportCenter;
  final bool pushNotifications;
  final List<PaymentOperatorOption> paymentOperators;

  const AccountCapabilities({
    required this.paymentMethods,
    required this.loyalty,
    required this.giftCards,
    required this.recentlyViewedSync,
    required this.supportCenter,
    required this.pushNotifications,
    required this.paymentOperators,
  });

  factory AccountCapabilities.fromJson(Map<String, dynamic> json) {
    final rawOperators = json['supported_payment_operators'] is List
        ? json['supported_payment_operators'] as List
        : const [];
    return AccountCapabilities(
      paymentMethods: json['payment_methods'] == true,
      loyalty: json['loyalty'] == true,
      giftCards: json['gift_cards'] == true,
      recentlyViewedSync: json['recently_viewed_sync'] == true,
      supportCenter: json['support_center'] == true,
      pushNotifications: json['push_notifications'] == true,
      paymentOperators: rawOperators
          .whereType<Map>()
          .map((item) => PaymentOperatorOption.fromJson(Map<String, dynamic>.from(item)))
          .toList(growable: false),
    );
  }
}

class PaymentOperatorOption {
  final String key;
  final String label;
  const PaymentOperatorOption({required this.key, required this.label});

  factory PaymentOperatorOption.fromJson(Map<String, dynamic> json) => PaymentOperatorOption(
        key: '${json['key'] ?? ''}'.trim(),
        label: cleanOvanieText('${json['label'] ?? ''}'),
      );
}

class SavedPaymentMethod {
  final int id;
  final String type;
  final String operator;
  final String operatorLabel;
  final String accountName;
  final String phone;
  final bool isDefault;
  final DateTime? lastUsedAt;
  final String cardBrand;
  final String cardLast4;
  final int? cardExpMonth;
  final int? cardExpYear;

  const SavedPaymentMethod({
    required this.id,
    required this.type,
    required this.operator,
    required this.operatorLabel,
    required this.accountName,
    required this.phone,
    required this.isDefault,
    required this.lastUsedAt,
    required this.cardBrand,
    required this.cardLast4,
    required this.cardExpMonth,
    required this.cardExpYear,
  });

  factory SavedPaymentMethod.fromJson(Map<String, dynamic> json) => SavedPaymentMethod(
        id: _toInt(json['id']),
        type: '${json['type'] ?? ''}'.trim(),
        operator: '${json['operator'] ?? ''}'.trim(),
        operatorLabel: cleanOvanieText('${json['operator_label'] ?? ''}'),
        accountName: cleanOvanieText('${json['account_name'] ?? ''}'),
        phone: '${json['phone'] ?? ''}'.trim(),
        isDefault: json['is_default'] == true,
        lastUsedAt: _toDate(json['last_used_at']),
        cardBrand: '${json['card_brand'] ?? ''}'.trim().toLowerCase(),
        cardLast4: '${json['card_last4'] ?? ''}'.trim(),
        cardExpMonth: json['card_exp_month'] == null ? null : _toInt(json['card_exp_month']),
        cardExpYear: json['card_exp_year'] == null ? null : _toInt(json['card_exp_year']),
      );

  String get cardBrandLabel {
    if (cardBrand == 'visa') return 'Visa';
    if (cardBrand == 'mastercard') return 'Mastercard';
    return 'Carte';
  }

  String get cardExpiryLabel {
    final month = cardExpMonth;
    final year = cardExpYear;
    if (month == null || year == null || month <= 0 || year <= 0) return '';
    final mm = month.toString().padLeft(2, '0');
    final yy = (year % 100).toString().padLeft(2, '0');
    return '$mm/$yy';
  }

  String get displayIdentifier {
    if (type == 'card') return '$cardBrandLabel •••• $cardLast4';
    return '$operatorLabel • $phone';
  }
}

class ClientDeviceSession {
  final String id;
  final String kind;
  final String name;
  final String ipAddress;
  final bool isCurrent;
  final DateTime? lastUsedAt;
  final DateTime? createdAt;

  const ClientDeviceSession({
    required this.id,
    required this.kind,
    required this.name,
    required this.ipAddress,
    required this.isCurrent,
    required this.lastUsedAt,
    required this.createdAt,
  });

  factory ClientDeviceSession.fromJson(Map<String, dynamic> json) => ClientDeviceSession(
        id: '${json['id'] ?? ''}',
        kind: '${json['kind'] ?? 'mobile'}',
        name: cleanOvanieText('${json['name'] ?? 'Appareil connecté'}'),
        ipAddress: '${json['ip_address'] ?? ''}',
        isCurrent: json['is_current'] == true,
        lastUsedAt: _toDate(json['last_used_at']),
        createdAt: _toDate(json['created_at']),
      );
}

class ClientNotificationItem {
  final String id;
  final String title;
  final String message;
  final String category;
  final String? url;
  final int? orderId;
  final DateTime? readAt;
  final DateTime? createdAt;

  const ClientNotificationItem({
    required this.id,
    required this.title,
    required this.message,
    required this.category,
    required this.url,
    required this.orderId,
    required this.readAt,
    required this.createdAt,
  });

  bool get isRead => readAt != null;

  factory ClientNotificationItem.fromJson(Map<String, dynamic> json) => ClientNotificationItem(
        id: '${json['id'] ?? ''}',
        title: cleanOvanieText('${json['title'] ?? 'Notification OVANIE'}'),
        message: cleanOvanieText('${json['message'] ?? ''}'),
        category: '${json['category'] ?? 'account'}'.trim(),
        url: json['url']?.toString(),
        orderId: json['order_id'] == null ? null : _toInt(json['order_id']),
        readAt: _toDate(json['read_at']),
        createdAt: _toDate(json['created_at']),
      );
}

class NotificationPageData {
  final List<ClientNotificationItem> items;
  final int unreadCount;
  final int currentPage;
  final int lastPage;

  const NotificationPageData({
    required this.items,
    required this.unreadCount,
    required this.currentPage,
    required this.lastPage,
  });
}

class NotificationPreferencesData {
  final Map<String, Map<String, bool>> preferences;
  final List<NotificationTypeOption> types;
  final List<NotificationChannelOption> channels;

  const NotificationPreferencesData({
    required this.preferences,
    required this.types,
    required this.channels,
  });

  factory NotificationPreferencesData.fromJson(Map<String, dynamic> json) {
    final prefs = <String, Map<String, bool>>{};
    final rawPrefs = json['preferences'];
    if (rawPrefs is Map) {
      for (final entry in rawPrefs.entries) {
        if (entry.value is Map) {
          prefs[entry.key.toString()] = Map<String, dynamic>.from(entry.value as Map)
              .map((key, value) => MapEntry(key, value == true));
        }
      }
    }
    final rawTypes = json['types'] is List ? json['types'] as List : const [];
    final rawChannels = json['channels'] is List ? json['channels'] as List : const [];
    return NotificationPreferencesData(
      preferences: prefs,
      types: rawTypes
          .whereType<Map>()
          .map((item) => NotificationTypeOption.fromJson(Map<String, dynamic>.from(item)))
          .toList(growable: false),
      channels: rawChannels
          .whereType<Map>()
          .map((item) => NotificationChannelOption.fromJson(Map<String, dynamic>.from(item)))
          .toList(growable: false),
    );
  }
}

class NotificationTypeOption {
  final String key;
  final String label;
  const NotificationTypeOption({required this.key, required this.label});
  factory NotificationTypeOption.fromJson(Map<String, dynamic> json) => NotificationTypeOption(
        key: '${json['key'] ?? ''}',
        label: cleanOvanieText('${json['label'] ?? ''}'),
      );
}

class NotificationChannelOption {
  final String key;
  final String label;
  final bool available;
  const NotificationChannelOption({required this.key, required this.label, required this.available});
  factory NotificationChannelOption.fromJson(Map<String, dynamic> json) => NotificationChannelOption(
        key: '${json['key'] ?? ''}',
        label: cleanOvanieText('${json['label'] ?? ''}'),
        available: json['available'] == true,
      );
}

class LoyaltyData {
  final int points;
  final int debt;
  final int pointValueXof;
  final double availableDiscountXof;
  final List<LoyaltyTransactionItem> transactions;

  const LoyaltyData({
    required this.points,
    required this.debt,
    required this.pointValueXof,
    required this.availableDiscountXof,
    required this.transactions,
  });

  factory LoyaltyData.fromJson(Map<String, dynamic> json) {
    final raw = json['transactions'] is List ? json['transactions'] as List : const [];
    return LoyaltyData(
      points: _toInt(json['points']),
      debt: _toInt(json['debt']),
      pointValueXof: _toInt(json['point_value_xof']),
      availableDiscountXof: _toDouble(json['available_discount_xof']),
      transactions: raw
          .whereType<Map>()
          .map((item) => LoyaltyTransactionItem.fromJson(Map<String, dynamic>.from(item)))
          .toList(growable: false),
    );
  }
}

class LoyaltyTransactionItem {
  final int id;
  final String type;
  final int points;
  final int balanceAfter;
  final String reference;
  final String description;
  final DateTime? createdAt;

  const LoyaltyTransactionItem({
    required this.id,
    required this.type,
    required this.points,
    required this.balanceAfter,
    required this.reference,
    required this.description,
    required this.createdAt,
  });

  factory LoyaltyTransactionItem.fromJson(Map<String, dynamic> json) => LoyaltyTransactionItem(
        id: _toInt(json['id']),
        type: '${json['type'] ?? ''}',
        points: _toInt(json['points']),
        balanceAfter: _toInt(json['balance_after']),
        reference: '${json['reference'] ?? ''}',
        description: cleanOvanieText('${json['description'] ?? ''}'),
        createdAt: _toDate(json['created_at']),
      );
}

class LegalDocumentItem {
  final String key;
  final String title;
  final bool available;
  final String? url;
  final String message;

  const LegalDocumentItem({
    required this.key,
    required this.title,
    required this.available,
    required this.url,
    required this.message,
  });

  factory LegalDocumentItem.fromJson(Map<String, dynamic> json) => LegalDocumentItem(
        key: '${json['key'] ?? ''}',
        title: cleanOvanieText('${json['title'] ?? ''}'),
        available: json['available'] == true,
        url: json['url']?.toString(),
        message: cleanOvanieText('${json['message'] ?? ''}'),
      );
}

class AccountDeletionStatus {
  final bool canDelete;
  final String reason;
  final bool socialAccount;
  final String confirmationWord;
  final bool codeRequired;
  final String email;

  const AccountDeletionStatus({
    required this.canDelete,
    required this.reason,
    required this.socialAccount,
    required this.confirmationWord,
    required this.codeRequired,
    required this.email,
  });

  factory AccountDeletionStatus.fromJson(Map<String, dynamic> json) => AccountDeletionStatus(
        canDelete: json['can_delete'] == true,
        reason: cleanOvanieText('${json['reason'] ?? ''}'),
        socialAccount: json['social_account'] == true,
        confirmationWord: '${json['confirmation_word'] ?? 'SUPPRIMER'}'.trim(),
        codeRequired: json['code_required'] == true,
        email: '${json['email'] ?? ''}'.trim(),
      );
}

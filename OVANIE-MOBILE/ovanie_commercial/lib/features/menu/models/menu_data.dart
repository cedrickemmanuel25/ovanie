class MenuProfileData {
  const MenuProfileData({
    required this.id,
    required this.firstName,
    required this.name,
    required this.email,
    required this.phone,
    required this.whatsapp,
    required this.avatarUrl,
    required this.employeeCode,
    required this.jobTitle,
    required this.city,
    required this.zone,
    required this.joinedAt,
    required this.active,
    required this.shopsOpened,
    required this.sessionsCount,
    required this.productsHandled,
  });

  final int id;
  final String firstName;
  final String name;
  final String email;
  final String phone;
  final String whatsapp;
  final String? avatarUrl;
  final String employeeCode;
  final String jobTitle;
  final String city;
  final String zone;
  final String joinedAt;
  final bool active;
  final int shopsOpened;
  final int sessionsCount;
  final int productsHandled;

  factory MenuProfileData.fromJson(Map<String, dynamic> json) {
    int number(dynamic value) => value is num ? value.toInt() : int.tryParse('${value ?? ''}') ?? 0;
    String text(dynamic value, [String fallback = '']) {
      final result = '${value ?? ''}'.trim();
      return result.isEmpty ? fallback : result;
    }

    final avatar = text(json['avatar_url']);
    return MenuProfileData(
      id: number(json['id']),
      firstName: text(json['first_name'], 'Commercial'),
      name: text(json['name'], 'Commercial OVANIE'),
      email: text(json['email']),
      phone: text(json['phone']),
      whatsapp: text(json['whatsapp']),
      avatarUrl: avatar.isEmpty ? null : avatar,
      employeeCode: text(json['employee_code'], '—'),
      jobTitle: text(json['job_title'], 'Commercial terrain'),
      city: text(json['city'], 'Abidjan'),
      zone: text(json['zone'], text(json['city'], 'Abidjan')),
      joinedAt: text(json['joined_at'], '—'),
      active: json['active'] != false,
      shopsOpened: number(json['shops_opened']),
      sessionsCount: number(json['sessions_count']),
      productsHandled: number(json['products_handled']),
    );
  }
}

class MenuOverviewData {
  const MenuOverviewData({
    required this.profile,
    required this.unreadNotifications,
    required this.visitedThisMonth,
    required this.sessionsInProgress,
    required this.draftsCount,
  });

  final MenuProfileData profile;
  final int unreadNotifications;
  final int visitedThisMonth;
  final int sessionsInProgress;
  final int draftsCount;

  factory MenuOverviewData.fromJson(Map<String, dynamic> json) {
    int number(dynamic value) => value is num ? value.toInt() : int.tryParse('${value ?? ''}') ?? 0;
    final profileRaw = json['profile'];
    return MenuOverviewData(
      profile: MenuProfileData.fromJson(
        profileRaw is Map ? Map<String, dynamic>.from(profileRaw) : const {},
      ),
      unreadNotifications: number(json['unread_notifications']),
      visitedThisMonth: number(json['visited_this_month']),
      sessionsInProgress: number(json['sessions_in_progress']),
      draftsCount: number(json['drafts_count']),
    );
  }
}

class MenuNotificationData {
  const MenuNotificationData({
    required this.id,
    required this.title,
    required this.message,
    required this.type,
    required this.read,
    required this.createdAt,
  });

  final String id;
  final String title;
  final String message;
  final String type;
  final bool read;
  final DateTime? createdAt;

  factory MenuNotificationData.fromJson(Map<String, dynamic> json) {
    final created = DateTime.tryParse('${json['created_at'] ?? ''}');
    return MenuNotificationData(
      id: '${json['id'] ?? ''}',
      title: '${json['title'] ?? 'Notification OVANIE'}'.trim(),
      message: '${json['message'] ?? ''}'.trim(),
      type: '${json['type'] ?? 'activity'}'.trim(),
      read: json['read'] == true || json['read_at'] != null,
      createdAt: created,
    );
  }
}

class MenuPreferencesData {
  const MenuPreferencesData({
    required this.newClients,
    required this.followUpReminders,
    required this.shopUpdates,
    required this.news,
    required this.language,
    required this.region,
    required this.theme,
    required this.autoSync,
    required this.wifiOnly,
    required this.shareLocation,
    required this.syncNotifications,
  });

  final bool newClients;
  final bool followUpReminders;
  final bool shopUpdates;
  final bool news;
  final String language;
  final String region;
  final String theme;
  final bool autoSync;
  final bool wifiOnly;
  final bool shareLocation;
  final bool syncNotifications;

  factory MenuPreferencesData.fromJson(Map<String, dynamic> json) {
    bool flag(String key, bool fallback) {
      final value = json[key];
      if (value is bool) return value;
      if (value is num) return value != 0;
      if (value is String) return value == '1' || value.toLowerCase() == 'true';
      return fallback;
    }

    String text(String key, String fallback) {
      final value = '${json[key] ?? ''}'.trim();
      return value.isEmpty ? fallback : value;
    }

    return MenuPreferencesData(
      newClients: flag('new_clients', true),
      followUpReminders: flag('follow_up_reminders', true),
      shopUpdates: flag('shop_updates', true),
      news: flag('news', false),
      language: text('language', 'Français'),
      region: text('region', 'Abidjan'),
      theme: text('theme', 'light'),
      autoSync: flag('auto_sync', true),
      wifiOnly: flag('wifi_only', false),
      shareLocation: flag('share_location', true),
      syncNotifications: flag('sync_notifications', true),
    );
  }

  Map<String, dynamic> toJson() => {
        'new_clients': newClients,
        'follow_up_reminders': followUpReminders,
        'shop_updates': shopUpdates,
        'news': news,
        'language': language,
        'region': region,
        'theme': theme,
        'auto_sync': autoSync,
        'wifi_only': wifiOnly,
        'share_location': shareLocation,
        'sync_notifications': syncNotifications,
      };

  MenuPreferencesData copyWith({
    bool? newClients,
    bool? followUpReminders,
    bool? shopUpdates,
    bool? news,
    String? language,
    String? region,
    String? theme,
    bool? autoSync,
    bool? wifiOnly,
    bool? shareLocation,
    bool? syncNotifications,
  }) {
    return MenuPreferencesData(
      newClients: newClients ?? this.newClients,
      followUpReminders: followUpReminders ?? this.followUpReminders,
      shopUpdates: shopUpdates ?? this.shopUpdates,
      news: news ?? this.news,
      language: language ?? this.language,
      region: region ?? this.region,
      theme: theme ?? this.theme,
      autoSync: autoSync ?? this.autoSync,
      wifiOnly: wifiOnly ?? this.wifiOnly,
      shareLocation: shareLocation ?? this.shareLocation,
      syncNotifications: syncNotifications ?? this.syncNotifications,
    );
  }
}

class VisitedShopData {
  const VisitedShopData({
    required this.activityId,
    required this.shopId,
    required this.name,
    required this.category,
    required this.location,
    required this.contact,
    required this.visitedAt,
    required this.nextAction,
    required this.status,
    required this.statusLabel,
    required this.productsCaptured,
    required this.imageUrl,
  });

  final int activityId;
  final int? shopId;
  final String name;
  final String category;
  final String location;
  final String contact;
  final DateTime? visitedAt;
  final DateTime? nextAction;
  final String status;
  final String statusLabel;
  final int productsCaptured;
  final String? imageUrl;

  factory VisitedShopData.fromJson(Map<String, dynamic> json) {
    int number(dynamic value) => value is num ? value.toInt() : int.tryParse('${value ?? ''}') ?? 0;
    int? nullableNumber(dynamic value) {
      if (value == null) return null;
      final result = number(value);
      return result == 0 ? null : result;
    }

    String text(dynamic value, [String fallback = '']) {
      final result = '${value ?? ''}'.trim();
      return result.isEmpty ? fallback : result;
    }

    final image = text(json['image_url']);
    return VisitedShopData(
      activityId: number(json['activity_id']),
      shopId: nullableNumber(json['shop_id']),
      name: text(json['name'], 'Boutique visitée'),
      category: text(json['category'], 'Boutique OVANIE'),
      location: text(json['location'], 'Localisation non renseignée'),
      contact: text(json['contact']),
      visitedAt: DateTime.tryParse(text(json['visited_at'])),
      nextAction: DateTime.tryParse(text(json['next_action_at'])),
      status: text(json['status'], 'visited'),
      statusLabel: text(json['status_label'], 'Visitée'),
      productsCaptured: number(json['products_captured']),
      imageUrl: image.isEmpty ? null : image,
    );
  }
}

class VisitedShopsData {
  const VisitedShopsData({
    required this.all,
    required this.today,
    required this.week,
    required this.followUp,
    required this.converted,
    required this.items,
  });

  final int all;
  final int today;
  final int week;
  final int followUp;
  final int converted;
  final List<VisitedShopData> items;

  factory VisitedShopsData.fromJson(Map<String, dynamic> json) {
    int number(dynamic value) => value is num ? value.toInt() : int.tryParse('${value ?? ''}') ?? 0;
    final summary = json['summary'] is Map
        ? Map<String, dynamic>.from(json['summary'] as Map)
        : const <String, dynamic>{};
    final raw = json['items'];
    return VisitedShopsData(
      all: number(summary['all']),
      today: number(summary['today']),
      week: number(summary['week']),
      followUp: number(summary['follow_up']),
      converted: number(summary['converted']),
      items: raw is List
          ? raw
              .whereType<Map>()
              .map((e) => VisitedShopData.fromJson(Map<String, dynamic>.from(e)))
              .toList(growable: false)
          : const [],
    );
  }
}

class DraftProductData {
  const DraftProductData({
    required this.id,
    required this.name,
    required this.reference,
    required this.category,
    required this.subcategory,
    required this.sessionName,
    required this.updatedAt,
    required this.completionStep,
    required this.imageUrl,
    required this.synced,
  });

  final int id;
  final String name;
  final String reference;
  final String category;
  final String subcategory;
  final String sessionName;
  final DateTime? updatedAt;
  final int completionStep;
  final String? imageUrl;
  final bool synced;

  double get progress => (completionStep.clamp(0, 5)) / 5;
  bool get almostReady => completionStep >= 4;

  factory DraftProductData.fromJson(Map<String, dynamic> json) {
    int number(dynamic value) => value is num ? value.toInt() : int.tryParse('${value ?? ''}') ?? 0;
    String text(dynamic value, [String fallback = '']) {
      final result = '${value ?? ''}'.trim();
      return result.isEmpty ? fallback : result;
    }

    final image = text(json['image_url']);
    return DraftProductData(
      id: number(json['id']),
      name: text(json['name'], 'Produit à compléter'),
      reference: text(json['reference'], '—'),
      category: text(json['category'], 'Produit OVANIE'),
      subcategory: text(json['subcategory']),
      sessionName: text(json['session_name'], 'Session produit'),
      updatedAt: DateTime.tryParse(text(json['updated_at'])),
      completionStep: number(json['completion_step']),
      imageUrl: image.isEmpty ? null : image,
      synced: json['synced'] != false,
    );
  }
}

class DraftProductsData {
  const DraftProductsData({required this.items});
  final List<DraftProductData> items;

  int get all => items.length;
  int get toComplete => items.where((e) => e.completionStep < 4).length;
  int get almostReady => items.where((e) => e.almostReady).length;

  factory DraftProductsData.fromJson(Map<String, dynamic> json) {
    final raw = json['items'];
    return DraftProductsData(
      items: raw is List
          ? raw
              .whereType<Map>()
              .map((e) => DraftProductData.fromJson(Map<String, dynamic>.from(e)))
              .toList(growable: false)
          : const [],
    );
  }
}

class SyncElementData {
  const SyncElementData({required this.title, required this.subtitle, required this.time});
  final String title;
  final String subtitle;
  final String time;

  factory SyncElementData.fromJson(Map<String, dynamic> json) => SyncElementData(
        title: '${json['title'] ?? ''}',
        subtitle: '${json['subtitle'] ?? ''}',
        time: '${json['time'] ?? ''}',
      );
}

class MenuSyncData {
  const MenuSyncData({
    required this.synced,
    required this.lastSync,
    required this.elements,
  });

  final bool synced;
  final DateTime? lastSync;
  final List<SyncElementData> elements;

  factory MenuSyncData.fromJson(Map<String, dynamic> json) {
    final raw = json['elements'];
    return MenuSyncData(
      synced: json['synced'] != false,
      lastSync: DateTime.tryParse('${json['last_sync'] ?? ''}'),
      elements: raw is List
          ? raw
              .whereType<Map>()
              .map((e) => SyncElementData.fromJson(Map<String, dynamic>.from(e)))
              .toList(growable: false)
          : const [],
    );
  }
}

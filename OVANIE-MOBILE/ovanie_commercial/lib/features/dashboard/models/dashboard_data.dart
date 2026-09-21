class CommercialProfile {
  const CommercialProfile({
    required this.name,
    required this.firstName,
    this.avatarUrl,
  });

  final String name;
  final String firstName;
  final String? avatarUrl;

  factory CommercialProfile.fromJson(Map<String, dynamic> json) {
    final name = (json['name'] ?? '').toString().trim();
    final firstNameRaw = (json['first_name'] ?? '').toString().trim();
    final firstName = firstNameRaw.isNotEmpty
        ? firstNameRaw
        : (name.isNotEmpty ? name.split(' ').first : 'Commercial');
    final avatar = (json['avatar_url'] ?? json['avatar'] ?? '').toString().trim();

    return CommercialProfile(
      name: name.isEmpty ? firstName : name,
      firstName: firstName,
      avatarUrl: avatar.isEmpty ? null : avatar,
    );
  }
}

class DashboardStat {
  const DashboardStat({required this.total, required this.today});

  final int total;
  final int today;

  factory DashboardStat.fromJson(dynamic json) {
    if (json is! Map) return const DashboardStat(total: 0, today: 0);
    return DashboardStat(
      total: _int(json['total']),
      today: _int(json['today']),
    );
  }
}

class DashboardActivity {
  const DashboardActivity({
    required this.type,
    required this.title,
    required this.subtitle,
    required this.status,
    required this.time,
    this.detail,
    this.progress,
  });

  final String type;
  final String title;
  final String subtitle;
  final String status;
  final String time;
  final String? detail;
  final double? progress;

  factory DashboardActivity.fromJson(dynamic json) {
    if (json is! Map) {
      return const DashboardActivity(
        type: 'activity',
        title: '',
        subtitle: '',
        status: '',
        time: '',
      );
    }
    return DashboardActivity(
      type: (json['type'] ?? 'activity').toString(),
      title: (json['title'] ?? '').toString(),
      subtitle: (json['subtitle'] ?? '').toString(),
      status: (json['status'] ?? '').toString(),
      time: (json['time'] ?? '').toString(),
      detail: json['detail']?.toString(),
      progress: json['progress'] == null ? null : _double(json['progress']),
    );
  }
}

class CommercialDashboardData {
  const CommercialDashboardData({
    required this.profile,
    required this.clients,
    required this.shops,
    required this.products,
    required this.toComplete,
    required this.activities,
    required this.shopsProspectedToday,
    required this.unreadNotifications,
    this.dailyObjectiveDone,
    this.dailyObjectiveTarget,
    this.monthlyProgress,
  });

  final CommercialProfile profile;
  final DashboardStat clients;
  final DashboardStat shops;
  final DashboardStat products;
  final int toComplete;
  final List<DashboardActivity> activities;
  final int shopsProspectedToday;
  final int unreadNotifications;
  final int? dailyObjectiveDone;
  final int? dailyObjectiveTarget;
  final double? monthlyProgress;

  factory CommercialDashboardData.fromJson(Map<String, dynamic> json) {
    final profileRaw = json['profile'];
    final statsRaw = json['stats'];
    final stats = statsRaw is Map ? statsRaw : const <String, dynamic>{};
    final summaryRaw = json['summary'];
    final summary = summaryRaw is Map ? summaryRaw : const <String, dynamic>{};
    final activityRaw = json['activities'];

    return CommercialDashboardData(
      profile: CommercialProfile.fromJson(
        profileRaw is Map
            ? Map<String, dynamic>.from(profileRaw)
            : const <String, dynamic>{},
      ),
      clients: DashboardStat.fromJson(stats['clients_created']),
      shops: DashboardStat.fromJson(stats['shops_opened']),
      products: DashboardStat.fromJson(stats['products_captured']),
      toComplete: _int(stats['products_to_complete']),
      activities: activityRaw is List
          ? activityRaw.map(DashboardActivity.fromJson).toList()
          : const [],
      shopsProspectedToday: _int(summary['shops_prospected_today']),
      unreadNotifications: _int(json['unread_notifications']),
      dailyObjectiveDone: _nullableInt(summary['daily_objective_done']),
      dailyObjectiveTarget: _nullableInt(summary['daily_objective_target']),
      monthlyProgress: summary['monthly_progress'] == null
          ? null
          : _double(summary['monthly_progress']),
    );
  }
}

int _int(dynamic value) {
  if (value is int) return value;
  if (value is num) return value.round();
  return int.tryParse(value?.toString() ?? '') ?? 0;
}

int? _nullableInt(dynamic value) {
  if (value == null) return null;
  if (value is int) return value;
  if (value is num) return value.round();
  return int.tryParse(value.toString());
}

double _double(dynamic value) {
  if (value is double) return value;
  if (value is num) return value.toDouble();
  return double.tryParse(value?.toString() ?? '') ?? 0;
}

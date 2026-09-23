import 'package:flutter/material.dart';

import '../../app/theme.dart';
import '../../core/config/api_config.dart';
import '../../core/location/driver_presence_service.dart';
import '../../core/network/api_client.dart';
import '../../core/push/push_notification_service.dart';
import '../../core/storage/token_storage.dart';
import '../../shared/widgets/phone_format.dart';
import '../auth/login_screen.dart';
import '../driver/data/driver_repository.dart';
import '../driver/models/driver_dashboard.dart';
import '../driver/models/driver_profile.dart';
import '../missions/widgets/mission_ui.dart';
import '../support/driver_support_screen.dart';

/// Onglet "Profil" : identité, véhicule, zones, disponibilité et actions du
/// compte. Les données viennent de `GET /driver/dashboard` (le même contrat
/// que l'Accueil) ; [driver] ne sert que de valeur de secours avant le
/// premier chargement.
class ProfileScreen extends StatefulWidget {
  const ProfileScreen({
    super.key,
    required this.driver,
    required this.onRefresh,
  });

  final DriverProfile driver;
  final Future<void> Function() onRefresh;

  @override
  State<ProfileScreen> createState() => _ProfileScreenState();
}

class _ProfileScreenState extends State<ProfileScreen> {
  DriverDashboardData? _dashboard;
  bool _loading = true;
  bool _updatingAvailability = false;
  bool _loggingOut = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load({bool silent = false}) async {
    if (mounted) {
      setState(() {
        _loading = !silent && _dashboard == null;
        _error = null;
      });
    }
    try {
      final dashboard = await DriverRepository.instance.dashboard();
      if (mounted) setState(() => _dashboard = dashboard);
    } catch (error) {
      if (mounted) setState(() => _error = ApiClient.friendlyError(error));
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _refresh() async {
    await widget.onRefresh();
    await _load();
  }

  Future<void> _setAvailability(bool available) async {
    final dashboard = _dashboard;
    if (dashboard == null || !dashboard.availability.canChange || _updatingAvailability) {
      return;
    }
    setState(() => _updatingAvailability = true);
    try {
      await DriverRepository.instance.updateAvailability(available ? 'Disponible' : 'Indisponible');
      await _load(silent: true);
    } catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(ApiClient.friendlyError(error))),
        );
      }
    } finally {
      if (mounted) setState(() => _updatingAvailability = false);
    }
  }

  void _comingSoon(String feature) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text('$feature : bientôt disponible.')),
    );
  }

  Future<void> _logout() async {
    if (_loggingOut) return;
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: const Text('Se déconnecter'),
        content: const Text('Voulez-vous vraiment vous déconnecter de votre compte livreur ?'),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(dialogContext).pop(false),
            child: const Text('Annuler'),
          ),
          TextButton(
            onPressed: () => Navigator.of(dialogContext).pop(true),
            style: TextButton.styleFrom(foregroundColor: MissionPalette.danger),
            child: const Text('Se déconnecter'),
          ),
        ],
      ),
    );
    if (confirmed != true || !mounted) return;

    setState(() => _loggingOut = true);
    try {
      await DriverPresenceService.instance.stop(notifyBackend: true);
      await PushNotificationService.instance.unregisterCurrentDevice();
      await TokenStorage.instance.clear();
      ApiClient.setBearerToken(null);
    } catch (_) {
      // La session locale est effacée même si le backend est injoignable.
    } finally {
      if (mounted) {
        Navigator.of(context, rootNavigator: true).pushAndRemoveUntil(
          MaterialPageRoute<void>(builder: (_) => const LoginScreen()),
          (route) => false,
        );
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final dashboard = _dashboard;
    final driver = dashboard?.driver;
    final fullName = (driver?.fullName.trim().isNotEmpty ?? false)
        ? driver!.fullName.trim()
        : widget.driver.fullName;
    final avatarUrl = driver?.avatarUrl ?? widget.driver.avatarUrl;
    final availabilityStatus = dashboard?.availability.status ?? 'Indisponible';
    final isAvailable = availabilityStatus == 'Disponible';
    final isOnMission = availabilityStatus == 'En mission';

    return Scaffold(
      backgroundColor: MissionPalette.mint,
      body: Column(
        children: [
          MissionPageHeader(
            title: 'Mon profil',
            subtitle: 'Vos informations OVANIE Logistics',
            mainPage: true,
            trailing: MissionNotificationButton(
              count: dashboard?.summary.unreadNotifications ?? 0,
              onTap: () => _comingSoon('Notifications'),
            ),
          ),
          Expanded(
            child: RefreshIndicator(
              color: OvanieColors.green,
              onRefresh: _refresh,
              child: ListView(
                physics: const AlwaysScrollableScrollPhysics(),
                padding: const EdgeInsets.fromLTRB(16, 14, 16, 22),
                children: [
                  if (_loading && dashboard == null)
                    const Padding(
                      padding: EdgeInsets.symmetric(vertical: 80),
                      child: Center(child: CircularProgressIndicator(color: OvanieColors.green)),
                    )
                  else ...[
                    if (_error != null) ...[
                      MissionSurfaceCard(
                        child: Row(
                          children: [
                            const Icon(Icons.cloud_off_rounded, color: OvanieColors.green),
                            const SizedBox(width: 10),
                            Expanded(
                              child: Text(
                                _error!,
                                style: const TextStyle(color: MissionPalette.slate, fontSize: 12.5),
                              ),
                            ),
                          ],
                        ),
                      ),
                      const SizedBox(height: 12),
                    ],
                    _IdentityCard(
                      fullName: fullName,
                      avatarUrl: avatarUrl,
                      isAvailable: isAvailable,
                      isOnMission: isOnMission,
                    ),
                    const SizedBox(height: 12),
                    _AccountCard(
                      fullName: fullName,
                      phone: widget.driver.phone,
                      email: widget.driver.email,
                    ),
                    const SizedBox(height: 12),
                    _VehicleCard(driver: driver),
                    const SizedBox(height: 12),
                    _ZonesCard(driver: driver),
                    const SizedBox(height: 12),
                    _AvailabilityCard(
                      status: availabilityStatus,
                      canChange: dashboard?.availability.canChange ?? false,
                      loading: _updatingAvailability,
                      onChanged: _setAvailability,
                    ),
                    const SizedBox(height: 12),
                    _AccountStatusCard(dashboard: dashboard),
                    const SizedBox(height: 12),
                    _SettingsCard(
                      onNotifications: () => _comingSoon('Notifications'),
                      onPrivacy: () => _comingSoon('Confidentialité'),
                      onSupport: () => Navigator.of(context).push(MaterialPageRoute<void>(builder: (_) => const DriverSupportScreen())),
                      onLogout: _loggingOut ? null : _logout,
                      loggingOut: _loggingOut,
                    ),
                  ],
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _IdentityCard extends StatelessWidget {
  const _IdentityCard({
    required this.fullName,
    required this.avatarUrl,
    required this.isAvailable,
    required this.isOnMission,
  });

  final String fullName;
  final String? avatarUrl;
  final bool isAvailable;
  final bool isOnMission;

  @override
  Widget build(BuildContext context) {
    final resolvedAvatar = ApiConfig.resolveMediaUrl(avatarUrl);
    final hasAvatar = resolvedAvatar.trim().isNotEmpty;
    final statusColor = isOnMission
        ? const Color(0xFF2D63B7)
        : isAvailable
            ? OvanieColors.green
            : MissionPalette.slate;
    final statusLabel = isOnMission ? 'En mission' : (isAvailable ? 'Disponible' : 'Indisponible');

    return MissionSurfaceCard(
      child: Row(
        children: [
          CircleAvatar(
            radius: 34,
            backgroundColor: OvanieColors.greenLight,
            backgroundImage: hasAvatar ? NetworkImage(resolvedAvatar) : null,
            onBackgroundImageError: hasAvatar ? (_, __) {} : null,
            child: hasAvatar
                ? null
                : const Icon(Icons.person_rounded, color: OvanieColors.greenDark, size: 34),
          ),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  fullName.isEmpty ? 'Livreur OVANIE' : fullName,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    color: MissionPalette.navy,
                    fontSize: 18,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 2),
                const Text(
                  'Livreur partenaire',
                  style: TextStyle(color: MissionPalette.slate, fontSize: 13, fontWeight: FontWeight.w500),
                ),
                const SizedBox(height: 9),
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 11, vertical: 6),
                  decoration: BoxDecoration(
                    color: const Color(0xFFE3F5EC),
                    borderRadius: BorderRadius.circular(999),
                  ),
                  child: const Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Icon(Icons.groups_rounded, size: 15, color: OvanieColors.green),
                      SizedBox(width: 5),
                      Text(
                        'Partenaire indépendant',
                        style: TextStyle(color: OvanieColors.green, fontSize: 11.5, fontWeight: FontWeight.w800),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 8),
                Row(
                  children: [
                    Container(
                      width: 9,
                      height: 9,
                      decoration: BoxDecoration(color: statusColor, shape: BoxShape.circle),
                    ),
                    const SizedBox(width: 6),
                    Text(
                      statusLabel,
                      style: TextStyle(color: statusColor, fontSize: 12.5, fontWeight: FontWeight.w800),
                    ),
                  ],
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _SectionCard extends StatelessWidget {
  const _SectionCard({
    required this.icon,
    required this.title,
    required this.subtitle,
    required this.child,
    this.onTap,
  });

  final IconData icon;
  final String title;
  final String subtitle;
  final Widget child;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    return MissionSurfaceCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          InkWell(
            onTap: onTap,
            borderRadius: BorderRadius.circular(12),
            child: Row(
              children: [
                Container(
                  width: 38,
                  height: 38,
                  decoration: BoxDecoration(
                    color: const Color(0xFFE3F5EC),
                    borderRadius: BorderRadius.circular(11),
                  ),
                  child: Icon(icon, color: OvanieColors.green, size: 20),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        title,
                        style: const TextStyle(
                          color: MissionPalette.navy,
                          fontSize: 15.5,
                          fontWeight: FontWeight.w900,
                        ),
                      ),
                      Text(
                        subtitle,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(color: MissionPalette.slate, fontSize: 11.8, fontWeight: FontWeight.w500),
                      ),
                    ],
                  ),
                ),
                if (onTap != null)
                  const Icon(Icons.chevron_right_rounded, color: MissionPalette.slate),
              ],
            ),
          ),
          const SizedBox(height: 12),
          child,
        ],
      ),
    );
  }
}

class _AccountCard extends StatelessWidget {
  const _AccountCard({required this.fullName, required this.phone, required this.email});

  final String fullName;
  final String phone;
  final String? email;

  @override
  Widget build(BuildContext context) {
    return _SectionCard(
      icon: Icons.person_rounded,
      title: 'Compte',
      subtitle: 'Informations personnelles',
      child: Column(
        children: [
          _FieldRow(icon: Icons.person_outline_rounded, label: 'Nom complet', value: fullName.isEmpty ? '—' : fullName),
          _FieldRow(
            icon: Icons.call_outlined,
            label: 'Téléphone',
            value: phone.isEmpty ? '—' : formatDriverPhone(phone),
          ),
          if ((email ?? '').trim().isNotEmpty)
            _FieldRow(icon: Icons.mail_outline_rounded, label: 'Email', value: email!.trim()),
        ],
      ),
    );
  }
}

class _FieldRow extends StatelessWidget {
  const _FieldRow({required this.icon, required this.label, required this.value});

  final IconData icon;
  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.only(bottom: 9),
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 13),
      decoration: BoxDecoration(
        color: const Color(0xFFF6FAF8),
        borderRadius: BorderRadius.circular(13),
        border: Border.all(color: MissionPalette.cardBorder),
      ),
      child: Row(
        children: [
          Icon(icon, size: 19, color: MissionPalette.slate),
          const SizedBox(width: 11),
          Expanded(
            child: Text(
              value,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: const TextStyle(color: MissionPalette.navy, fontSize: 13.6, fontWeight: FontWeight.w700),
            ),
          ),
        ],
      ),
    );
  }
}

const _vehicleLabels = {
  'moto': 'Moto',
  'tricycle': 'Tricycle',
  'pickup': 'Pickup',
  'camion_3t': 'Camion 3T',
  'camion_10t': 'Camion 10T',
};

String _vehicleTypeLabel(String? code) {
  final value = (code ?? '').trim();
  if (value.isEmpty) return 'Non renseigné';
  return _vehicleLabels[value] ?? value;
}

String? _vehicleAssetPath(String? code) {
  final value = (code ?? '').toLowerCase().trim();
  if (value.contains('tricycle')) return 'assets/logistics-tricycle.png';
  if (value.contains('moto')) return 'assets/logistics-moto.png';
  if (value.contains('pickup')) return 'assets/logistics-pickup.png';
  if (value.contains('10t')) return 'assets/logistics-camion-10t.png';
  if (value.contains('3t')) return 'assets/logistics-camion-3t.png';
  return null;
}

Color? _parseVehicleHex(String? value) {
  final input = (value ?? '').replaceAll('#', '').trim();
  if (input.length != 6) return null;
  final parsed = int.tryParse(input, radix: 16);
  return parsed == null ? null : Color(0xFF000000 | parsed);
}

Color? _vehicleColorFromLabel(String? label) {
  final value = (label ?? '').toLowerCase().trim();
  if (value.isEmpty) return null;
  if (value.contains('argent') || value.contains('gris clair')) return const Color(0xFFA7ADB3);
  if (value.contains('gris foncé') || value.contains('gris fonce')) return const Color(0xFF5E646B);
  if (value == 'gris') return const Color(0xFF80868D);
  if (value.contains('noir')) return const Color(0xFF202428);
  if (value.contains('blanc')) return const Color(0xFFE9EDF0);
  if (value.contains('rouge')) return const Color(0xFFD9342B);
  if (value.contains('orange')) return const Color(0xFFF28B24);
  if (value.contains('jaune')) return const Color(0xFFE3B316);
  if (value.contains('vert')) return const Color(0xFF159447);
  if (value.contains('bleu')) return const Color(0xFF2D63B7);
  if (value.contains('violet')) return const Color(0xFF7250A8);
  if (value.contains('rose') || value.contains('bordeaux')) return const Color(0xFFA53757);
  return null;
}

class _VehicleCard extends StatelessWidget {
  const _VehicleCard({required this.driver});

  final DriverDashboardDriver? driver;

  @override
  Widget build(BuildContext context) {
    final assetPath = _vehicleAssetPath(driver?.vehicleCode ?? driver?.vehicle);
    final realPhotoUrl = driver?.vehiclePhotoIsReal == true
        ? ApiConfig.resolveMediaUrl(driver?.vehiclePhotoUrl)
        : '';
    final tintColor = _parseVehicleHex(driver?.vehicleColorHex) ?? _vehicleColorFromLabel(driver?.vehicleColor);

    Widget categoryFallback() => assetPath == null
        ? const Icon(Icons.local_shipping_rounded, color: MissionPalette.slate, size: 40)
        // srcATop reste calé sur l'alpha du PNG : évite le carré opaque que
        // produirait BlendMode.color sur un fond transparent.
        : Image.asset(
            assetPath,
            fit: BoxFit.contain,
            filterQuality: FilterQuality.high,
            color: tintColor,
            colorBlendMode: tintColor == null ? null : BlendMode.srcATop,
          );

    return _SectionCard(
      icon: Icons.local_shipping_rounded,
      title: 'Mon véhicule',
      subtitle: 'Véhicule enregistré pour les livraisons',
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 128,
            height: 96,
            clipBehavior: Clip.antiAlias,
            decoration: BoxDecoration(
              color: const Color(0xFFF6FAF8),
              borderRadius: BorderRadius.circular(13),
              border: Border.all(color: MissionPalette.cardBorder),
            ),
            padding: realPhotoUrl.isEmpty ? const EdgeInsets.all(12) : EdgeInsets.zero,
            child: realPhotoUrl.isEmpty
                ? categoryFallback()
                : Image.network(
                    realPhotoUrl,
                    fit: BoxFit.cover,
                    filterQuality: FilterQuality.high,
                    errorBuilder: (_, __, ___) => Padding(
                      padding: const EdgeInsets.all(12),
                      child: categoryFallback(),
                    ),
                  ),
          ),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              children: [
                _FieldRow(
                  icon: Icons.two_wheeler_rounded,
                  label: 'Type',
                  value: _vehicleTypeLabel(driver?.vehicleCode ?? driver?.vehicle),
                ),
                _FieldRow(
                  icon: Icons.palette_outlined,
                  label: 'Couleur',
                  value: (driver?.vehicleColor ?? '').trim().isEmpty ? 'Non renseignée' : driver!.vehicleColor!.trim(),
                ),
                _FieldRow(
                  icon: Icons.confirmation_number_outlined,
                  label: 'Immatriculation',
                  value: (driver?.vehiclePlate ?? '').trim().isEmpty ? 'Non renseignée' : driver!.vehiclePlate!.trim(),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _ZonesCard extends StatelessWidget {
  const _ZonesCard({required this.driver});

  final DriverDashboardDriver? driver;

  @override
  Widget build(BuildContext context) {
    final zones = driver?.zones ?? const <String>[];
    return _SectionCard(
      icon: Icons.location_on_rounded,
      title: 'Zones d\'intervention',
      subtitle: 'Zones dans lesquelles vous pouvez recevoir des missions',
      child: zones.isEmpty
          ? const Text(
              'Aucune zone renseignée.',
              style: TextStyle(color: MissionPalette.slate, fontSize: 13),
            )
          : Wrap(
              spacing: 8,
              runSpacing: 8,
              children: zones.map((zone) => _ZoneChip(label: zone)).toList(growable: false),
            ),
    );
  }
}

class _ZoneChip extends StatelessWidget {
  const _ZoneChip({required this.label});

  final String label;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 13, vertical: 8),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(999),
        border: Border.all(color: MissionPalette.cardBorder),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          const Icon(Icons.location_on_rounded, size: 15, color: OvanieColors.green),
          const SizedBox(width: 5),
          Text(
            label,
            style: const TextStyle(color: MissionPalette.navy, fontSize: 12.6, fontWeight: FontWeight.w700),
          ),
        ],
      ),
    );
  }
}

class _AvailabilityCard extends StatelessWidget {
  const _AvailabilityCard({
    required this.status,
    required this.canChange,
    required this.loading,
    required this.onChanged,
  });

  final String status;
  final bool canChange;
  final bool loading;
  final ValueChanged<bool> onChanged;

  @override
  Widget build(BuildContext context) {
    final isAvailable = status == 'Disponible';
    return MissionSurfaceCard(
      child: Row(
        children: [
          Container(
            width: 38,
            height: 38,
            decoration: BoxDecoration(
              color: const Color(0xFFE3F5EC),
              borderRadius: BorderRadius.circular(11),
            ),
            child: const Icon(Icons.toggle_on_rounded, color: OvanieColors.green, size: 22),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text(
                  'Disponibilité',
                  style: TextStyle(color: MissionPalette.navy, fontSize: 15.5, fontWeight: FontWeight.w900),
                ),
                const SizedBox(height: 2),
                Text(
                  status,
                  style: TextStyle(
                    color: isAvailable ? OvanieColors.green : MissionPalette.slate,
                    fontSize: 13,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                const SizedBox(height: 3),
                const Text(
                  'Lorsque vous êtes disponible, OVANIE Logistics peut vous affecter des missions.',
                  style: TextStyle(color: MissionPalette.slate, fontSize: 11.5, height: 1.35),
                ),
              ],
            ),
          ),
          if (loading)
            const SizedBox(
              width: 24,
              height: 24,
              child: CircularProgressIndicator(strokeWidth: 2.4, color: OvanieColors.green),
            )
          else
            Switch(
              value: isAvailable,
              onChanged: canChange ? onChanged : null,
              activeThumbColor: OvanieColors.green,
            ),
        ],
      ),
    );
  }
}

class _AccountStatusCard extends StatelessWidget {
  const _AccountStatusCard({required this.dashboard});

  final DriverDashboardData? dashboard;

  @override
  Widget build(BuildContext context) {
    final account = dashboard?.account;
    final driver = dashboard?.driver;
    final accountValidated = account?.isActive == true && account?.status == 'active';
    final vehicleRegistered =
        (driver?.vehicle ?? '').trim().isNotEmpty && (driver?.vehiclePlate ?? '').trim().isNotEmpty;
    final gpsAuthorized = DriverPresenceService.instance.gpsAvailable;

    final items = <(String, bool)>[
      ('Compte validé', accountValidated),
      ('Numéro vérifié', accountValidated),
      ('Véhicule enregistré', vehicleRegistered),
      ('GPS autorisé', gpsAuthorized),
    ];
    final allValid = items.every((item) => item.$2);

    return _SectionCard(
      icon: Icons.verified_user_rounded,
      title: 'État du compte',
      subtitle: allValid ? 'Tous les éléments sont validés' : 'Certains éléments restent à valider',
      child: Wrap(
        spacing: 8,
        runSpacing: 8,
        children: items.map((item) => _StatusChip(label: item.$1, ok: item.$2)).toList(growable: false),
      ),
    );
  }
}

class _StatusChip extends StatelessWidget {
  const _StatusChip({required this.label, required this.ok});

  final String label;
  final bool ok;

  @override
  Widget build(BuildContext context) {
    final color = ok ? OvanieColors.green : MissionPalette.amber;
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
      decoration: BoxDecoration(
        color: ok ? const Color(0xFFDFF7EC) : MissionPalette.amberSoft,
        borderRadius: BorderRadius.circular(999),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(ok ? Icons.check_circle_rounded : Icons.error_outline_rounded, size: 16, color: color),
          const SizedBox(width: 6),
          Text(label, style: TextStyle(color: color, fontSize: 12, fontWeight: FontWeight.w800)),
        ],
      ),
    );
  }
}

class _SettingsCard extends StatelessWidget {
  const _SettingsCard({
    required this.onNotifications,
    required this.onPrivacy,
    required this.onSupport,
    required this.onLogout,
    required this.loggingOut,
  });

  final VoidCallback onNotifications;
  final VoidCallback onPrivacy;
  final VoidCallback onSupport;
  final VoidCallback? onLogout;
  final bool loggingOut;

  @override
  Widget build(BuildContext context) {
    return MissionSurfaceCard(
      padding: const EdgeInsets.symmetric(vertical: 6),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          const Padding(
            padding: EdgeInsets.fromLTRB(12, 10, 12, 4),
            child: Row(
              children: [
                Icon(Icons.settings_rounded, color: OvanieColors.green, size: 20),
                SizedBox(width: 9),
                Text(
                  'Paramètres et actions',
                  style: TextStyle(color: MissionPalette.navy, fontSize: 15.5, fontWeight: FontWeight.w900),
                ),
              ],
            ),
          ),
          const Divider(height: 1, color: MissionPalette.cardBorder),
          _SettingsRow(icon: Icons.notifications_none_rounded, label: 'Notifications', onTap: onNotifications),
          _SettingsRow(icon: Icons.lock_outline_rounded, label: 'Confidentialité', onTap: onPrivacy),
          _SettingsRow(icon: Icons.help_outline_rounded, label: 'Aide & support', onTap: onSupport),
          _SettingsRow(
            icon: Icons.logout_rounded,
            label: loggingOut ? 'Déconnexion...' : 'Se déconnecter',
            danger: true,
            onTap: onLogout,
            trailing: loggingOut
                ? const SizedBox(
                    width: 18,
                    height: 18,
                    child: CircularProgressIndicator(strokeWidth: 2.2, color: MissionPalette.danger),
                  )
                : null,
          ),
        ],
      ),
    );
  }
}

class _SettingsRow extends StatelessWidget {
  const _SettingsRow({
    required this.icon,
    required this.label,
    required this.onTap,
    this.danger = false,
    this.trailing,
  });

  final IconData icon;
  final String label;
  final VoidCallback? onTap;
  final bool danger;
  final Widget? trailing;

  @override
  Widget build(BuildContext context) {
    final color = danger ? MissionPalette.danger : MissionPalette.navy;
    return InkWell(
      onTap: onTap,
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 13),
        child: Row(
          children: [
            Icon(icon, size: 20, color: color),
            const SizedBox(width: 13),
            Expanded(
              child: Text(
                label,
                style: TextStyle(color: color, fontSize: 14, fontWeight: FontWeight.w700),
              ),
            ),
            trailing ?? Icon(Icons.chevron_right_rounded, color: color.withValues(alpha: .6)),
          ],
        ),
      ),
    );
  }
}

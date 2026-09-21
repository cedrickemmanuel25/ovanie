import 'dart:async';

import 'package:flutter/material.dart';

import '../../app/theme.dart';
import '../../core/config/api_config.dart';
import '../../core/location/driver_presence_service.dart';
import '../../core/network/api_client.dart';
import '../../shared/widgets/ovanie_widgets.dart';
import '../driver/data/driver_repository.dart';
import '../driver/models/driver_dashboard.dart';
import '../driver/models/driver_profile.dart';
import '../missions/screens/mission_accepted_screen.dart';
import '../missions/screens/mission_collecting_screen.dart';
import '../missions/screens/mission_departure_screen.dart';
import '../missions/screens/mission_detail_screen.dart';

/// Accueil opérationnel OVANIE Livreur.
///
/// Toutes les informations sont issues du backend : profil, Flotte, vraie photo
/// du véhicule, couleur détectée depuis cette photo, disponibilité, missions et
/// notifications. Aucun chiffre ni véhicule de démonstration n'est injecté ici.
class DriverHomeTab extends StatefulWidget {
  const DriverHomeTab({super.key, required this.driver, required this.onRefresh});

  final DriverProfile driver;
  final Future<void> Function() onRefresh;

  @override
  State<DriverHomeTab> createState() => _DriverHomeTabState();
}

class _DriverHomeTabState extends State<DriverHomeTab> {
  DriverDashboardData? _dashboard;
  bool _loading = true;
  bool _updatingAvailability = false;
  String? _error;
  Timer? _liveTimer;

  @override
  void initState() {
    super.initState();
    _load();
    _liveTimer = Timer.periodic(const Duration(seconds: 10), (_) {
      if (mounted) _load(silent: true);
    });
  }

  @override
  void dispose() {
    _liveTimer?.cancel();
    super.dispose();
  }

  Future<void> _load({bool silent = false}) async {
    if (mounted) {
      setState(() {
        _loading = !silent && _dashboard == null;
        _error = null;
      });
    }
    try {
      await DriverPresenceService.instance.refreshNow();
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

  /// La carte "Mission prioritaire" n'avait aucune action : impossible
  /// d'accepter une mission fraîchement affectée sans aller chercher l'onglet
  /// Missions (qui pouvait lui-même afficher une liste obsolète). On ouvre
  /// directement le bon écran selon le statut, comme le fait déjà l'onglet Suivi.
  Future<void> _openPriorityMission(DriverMissionSummary mission) async {
    Widget screen;
    switch (mission.status) {
      case 'accepted':
        screen = MissionAcceptedScreen(missionNumber: mission.missionNumber);
      case 'collecting':
        screen = MissionCollectingScreen(missionNumber: mission.missionNumber);
      case 'picked_up':
      case 'in_transit':
      case 'arrived':
        screen = MissionDepartureScreen(missionNumber: mission.missionNumber);
      default:
        screen = MissionDetailScreen(missionNumber: mission.missionNumber);
    }

    await Navigator.of(context).push(
      MaterialPageRoute<void>(builder: (_) => screen),
    );
    if (mounted) await _load(silent: true);
  }

  Future<void> _setAvailability(bool available) async {
    final dashboard = _dashboard;
    if (dashboard == null || !dashboard.availability.canChange || _updatingAvailability) return;

    setState(() => _updatingAvailability = true);
    try {
      await DriverRepository.instance.updateAvailability(available ? 'Disponible' : 'Indisponible');
      await _load();
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

  @override
  Widget build(BuildContext context) {
    final dashboard = _dashboard;
    final driver = dashboard?.driver;
    final firstName = (driver?.firstName.trim().isNotEmpty ?? false)
        ? driver!.firstName.trim()
        : widget.driver.firstName.trim();

    return Scaffold(
      backgroundColor: const Color(0xFFF3F6F4),
      body: SafeArea(
        bottom: false,
        child: RefreshIndicator(
          onRefresh: _refresh,
          color: OvanieColors.green,
          child: ListView(
            physics: const AlwaysScrollableScrollPhysics(),
            padding: EdgeInsets.zero,
            children: [
              _DriverTopHeader(
                firstName: firstName,
                fullName: driver?.fullName ?? '${widget.driver.firstName} ${widget.driver.lastName}'.trim(),
                avatarUrl: driver?.avatarUrl ?? widget.driver.avatarUrl,
                isOnline: driver?.isOnline ?? false,
                accountActive: dashboard?.account.isActive == true && dashboard?.account.status == 'active',
                unreadNotifications: dashboard?.summary.unreadNotifications ?? 0,
                onRefresh: _loading ? null : _refresh,
              ),
              if (_loading && dashboard == null)
                const LinearProgressIndicator(color: OvanieColors.green, minHeight: 3),
              Padding(
                padding: const EdgeInsets.fromLTRB(16, 16, 16, 28),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    if (_error != null) ...[
                      OvanieErrorBox(_error),
                      const SizedBox(height: 14),
                    ],
                    if (dashboard != null) ...[
                      _RegisteredVehicleCard(driver: dashboard.driver),
                      const SizedBox(height: 14),
                      _AvailabilityPanel(
                        state: dashboard.availability,
                        loading: _updatingAvailability,
                        onChanged: _setAvailability,
                      ),
                      const SizedBox(height: 14),
                      _SummaryStrip(summary: dashboard.summary),
                      const SizedBox(height: 24),
                      _SectionHeader(
                        title: 'Mission prioritaire',
                        trailing: dashboard.priorityMission == null ? null : dashboard.priorityMission!.statusLabel,
                      ),
                      const SizedBox(height: 10),
                      _PriorityMissionCard(
                        mission: dashboard.priorityMission,
                        canReceiveMissions: dashboard.availability.canReceiveMissions,
                        onTap: dashboard.priorityMission == null
                            ? null
                            : () => _openPriorityMission(dashboard.priorityMission!),
                      ),
                      if (dashboard.currentMission != null) ...[
                        const SizedBox(height: 22),
                        const _SectionHeader(title: 'Mission en cours'),
                        const SizedBox(height: 10),
                        _MissionCompactCard(
                          mission: dashboard.currentMission!,
                          accent: OvanieColors.green,
                        ),
                      ],
                      if (dashboard.nextMission != null) ...[
                        const SizedBox(height: 18),
                        const _SectionHeader(title: 'Prochaine mission'),
                        const SizedBox(height: 10),
                        _MissionCompactCard(
                          mission: dashboard.nextMission!,
                          accent: const Color(0xFFB07916),
                        ),
                      ],
                      if (dashboard.notifications.isNotEmpty || dashboard.summary.unreadNotifications > 0) ...[
                        const SizedBox(height: 22),
                        _NotificationsCard(
                          notifications: dashboard.notifications,
                          unreadCount: dashboard.summary.unreadNotifications,
                        ),
                      ],
                    ] else if (!_loading)
                      const OvanieInfoBox(
                        child: Text(
                          'Les données opérationnelles seront affichées dès que la connexion au serveur OVANIE sera rétablie.',
                          style: TextStyle(color: OvanieColors.text, height: 1.4),
                        ),
                      ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _DriverTopHeader extends StatelessWidget {
  const _DriverTopHeader({
    required this.firstName,
    required this.fullName,
    required this.avatarUrl,
    required this.isOnline,
    required this.accountActive,
    required this.unreadNotifications,
    required this.onRefresh,
  });

  final String firstName;
  final String fullName;
  final String? avatarUrl;
  final bool isOnline;
  final bool accountActive;
  final int unreadNotifications;
  final Future<void> Function()? onRefresh;

  @override
  Widget build(BuildContext context) {
    final avatar = ApiConfig.resolveMediaUrl(avatarUrl);

    Widget logo() => const Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(
              'OVANIE',
              style: TextStyle(
                color: Colors.white,
                fontSize: 25,
                fontWeight: FontWeight.w900,
                letterSpacing: .7,
              ),
            ),
            Text(
              'Logistics',
              style: TextStyle(
                color: Color(0xFF8DF0B7),
                fontSize: 13,
                fontWeight: FontWeight.w800,
                letterSpacing: .6,
              ),
            ),
          ],
        );

    Widget notificationButton() => Stack(
          clipBehavior: Clip.none,
          children: [
            Container(
              width: 42,
              height: 42,
              decoration: BoxDecoration(
                color: Colors.white.withValues(alpha: .12),
                shape: BoxShape.circle,
              ),
              child: const Icon(
                Icons.notifications_none_rounded,
                color: Colors.white,
                size: 23,
              ),
            ),
            if (unreadNotifications > 0)
              Positioned(
                right: -2,
                top: -3,
                child: Container(
                  constraints: const BoxConstraints(minWidth: 20, minHeight: 20),
                  padding: const EdgeInsets.symmetric(horizontal: 5),
                  decoration: const BoxDecoration(
                    color: Color(0xFFE63B43),
                    shape: BoxShape.circle,
                  ),
                  alignment: Alignment.center,
                  child: Text(
                    unreadNotifications > 9 ? '9+' : '$unreadNotifications',
                    style: const TextStyle(
                      color: Colors.white,
                      fontSize: 9,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                ),
              ),
          ],
        );

    Widget avatarWidget() => Container(
          width: 48,
          height: 48,
          clipBehavior: Clip.antiAlias,
          decoration: BoxDecoration(
            color: Colors.white.withValues(alpha: .18),
            shape: BoxShape.circle,
            border: Border.all(
              color: Colors.white.withValues(alpha: .75),
              width: 2,
            ),
          ),
          child: avatar.isNotEmpty
              ? Image.network(
                  avatar,
                  fit: BoxFit.cover,
                  errorBuilder: (_, __, ___) => const Icon(
                    Icons.person_rounded,
                    color: Colors.white,
                  ),
                )
              : const Icon(Icons.person_rounded, color: Colors.white),
        );

    Widget identity() => Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(
              fullName.isEmpty ? 'Livreur OVANIE' : fullName,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: const TextStyle(
                color: Colors.white,
                fontSize: 13.5,
                fontWeight: FontWeight.w900,
              ),
            ),
            const SizedBox(height: 3),
            Row(
              children: [
                Icon(
                  accountActive
                      ? Icons.verified_rounded
                      : Icons.pending_outlined,
                  color: accountActive
                      ? const Color(0xFF8DF0B7)
                      : Colors.white70,
                  size: 14,
                ),
                const SizedBox(width: 4),
                Expanded(
                  child: Text(
                    accountActive
                        ? 'Compte actif'
                        : 'Compte en vérification',
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: TextStyle(
                      color: Colors.white.withValues(alpha: .88),
                      fontSize: 10.5,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                ),
              ],
            ),
          ],
        );

    return Container(
      decoration: const BoxDecoration(
        gradient: LinearGradient(
          colors: [Color(0xFF006B3D), Color(0xFF009B57)],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        borderRadius: BorderRadius.only(
          bottomLeft: Radius.circular(30),
          bottomRight: Radius.circular(30),
        ),
      ),
      padding: const EdgeInsets.fromLTRB(18, 14, 18, 24),
      child: Column(
        children: [
          LayoutBuilder(
            builder: (context, constraints) {
              // Sur les téléphones étroits, l'identité passe sur une deuxième
              // ligne : aucun RenderFlex ne peut alors dépasser horizontalement
              // (suppression des bandes jaune/noire et du texte rouge OVERFLOW).
              if (constraints.maxWidth < 390) {
                return Column(
                  children: [
                    Row(
                      children: [
                        Expanded(child: logo()),
                        notificationButton(),
                      ],
                    ),
                    const SizedBox(height: 14),
                    Row(
                      children: [
                        avatarWidget(),
                        const SizedBox(width: 10),
                        Expanded(child: identity()),
                      ],
                    ),
                  ],
                );
              }

              return Row(
                children: [
                  Expanded(child: logo()),
                  notificationButton(),
                  const SizedBox(width: 12),
                  avatarWidget(),
                  const SizedBox(width: 9),
                  SizedBox(width: 148, child: identity()),
                ],
              );
            },
          ),
          const SizedBox(height: 24),
          Row(
            crossAxisAlignment: CrossAxisAlignment.end,
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      firstName.isEmpty ? 'Bonjour !' : 'Bonjour $firstName !',
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        color: Colors.white,
                        fontSize: 29,
                        fontWeight: FontWeight.w900,
                        letterSpacing: -.7,
                      ),
                    ),
                    const SizedBox(height: 5),
                    Text(
                      'Prêt pour vos missions OVANIE Logistics ?',
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: TextStyle(
                        color: Colors.white.withValues(alpha: .82),
                        fontSize: 13,
                        fontWeight: FontWeight.w500,
                      ),
                    ),
                    const SizedBox(height: 12),
                    Align(
                      alignment: Alignment.centerLeft,
                      child: Container(
                        padding: const EdgeInsets.symmetric(
                          horizontal: 10,
                          vertical: 7,
                        ),
                        decoration: BoxDecoration(
                          color: Colors.white.withValues(alpha: .12),
                          borderRadius: BorderRadius.circular(999),
                        ),
                        child: Row(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            Icon(
                              isOnline
                                  ? Icons.gps_fixed_rounded
                                  : Icons.gps_off_rounded,
                              color: Colors.white,
                              size: 15,
                            ),
                            const SizedBox(width: 6),
                            Text(
                              isOnline ? 'GPS en ligne' : 'GPS hors ligne',
                              style: const TextStyle(
                                color: Colors.white,
                                fontSize: 11,
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
              const SizedBox(width: 8),
              IconButton(
                tooltip: 'Actualiser',
                onPressed: onRefresh == null ? null : () => onRefresh!(),
                icon: const Icon(Icons.refresh_rounded),
                color: Colors.white,
                style: IconButton.styleFrom(
                  backgroundColor: Colors.white.withValues(alpha: .12),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

String _vehicleAssetPath(String type) {
  final value = type.toLowerCase().replaceAll('-', ' ').replaceAll('_', ' ').trim();
  if (value.contains('tricycle') || value.contains('triporteur')) return 'assets/vehicles/tricycle.png';
  if (value.contains('moto') || value.contains('scooter')) return 'assets/vehicles/moto.png';
  if (value.contains('pickup') || value.contains('pick up')) return 'assets/vehicles/pickup.png';
  if (value.contains('10t') || value.contains('10 t') || value.contains('10 tonne')) return 'assets/vehicles/camion-10t.png';
  if (value.contains('3t') || value.contains('3 t') || value.contains('3 tonne')) return 'assets/vehicles/camion-3t.png';
  if (value.contains('camion')) return 'assets/vehicles/camion-3t.png';
  return 'assets/vehicles/pickup.png';
}

class _RegisteredVehicleCard extends StatelessWidget {
  const _RegisteredVehicleCard({required this.driver});

  final DriverDashboardDriver driver;

  @override
  Widget build(BuildContext context) {
    final plate = (driver.vehiclePlate ?? '').trim();
    final color = (driver.vehicleColor ?? '').trim();
    final type = (driver.vehicle ?? '').trim().isEmpty
        ? 'Véhicule'
        : driver.vehicle!.trim();
    final makeModel = [driver.vehicleBrand, driver.vehicleModel]
        .where((e) => (e ?? '').trim().isNotEmpty)
        .join(' ');
    final zoneLabel = driver.interventionZonesLabel;
    final colorValue = _parseHexColor(driver.vehicleColorHex) ??
        _vehicleColorFromLabel(color) ??
        OvanieColors.green;
    final assetPath = _vehicleAssetPath(type);
    final realPhotoUrl = driver.vehiclePhotoIsReal
        ? ApiConfig.resolveMediaUrl(driver.vehiclePhotoUrl)
        : '';

    Widget categoryFallback() => Container(
          color: const Color(0xFFF7FAF8),
          alignment: Alignment.center,
          padding: const EdgeInsets.all(18),
          // BlendMode.color (via ColorFiltered) peint tout le rectangle en
          // opaque sur certains moteurs de rendu au lieu de ne teinter que la
          // silhouette du véhicule : srcATop reste calé sur l'alpha du PNG.
          child: Image.asset(
            assetPath,
            fit: BoxFit.contain,
            filterQuality: FilterQuality.high,
            color: colorValue,
            colorBlendMode: BlendMode.srcATop,
            errorBuilder: (_, __, ___) => Icon(
              _vehicleIcon(type),
              color: colorValue,
              size: 62,
            ),
          ),
        );

    Widget vehicleVisual() {
      if (realPhotoUrl.isEmpty) return categoryFallback();

      return Stack(
        fit: StackFit.expand,
        children: [
          Image.network(
            realPhotoUrl,
            fit: BoxFit.cover,
            filterQuality: FilterQuality.high,
            errorBuilder: (_, __, ___) => categoryFallback(),
          ),
          Positioned(
            left: 10,
            bottom: 10,
            child: Container(
              padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 5),
              decoration: BoxDecoration(
                color: Colors.black.withValues(alpha: .62),
                borderRadius: BorderRadius.circular(999),
              ),
              child: const Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Icon(Icons.photo_camera_rounded, color: Colors.white, size: 13),
                  SizedBox(width: 5),
                  Text(
                    'Photo enregistrée',
                    style: TextStyle(
                      color: Colors.white,
                      fontSize: 9.5,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ],
              ),
            ),
          ),
        ],
      );
    }

    final info = Padding(
      padding: const EdgeInsets.fromLTRB(16, 15, 16, 16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        mainAxisSize: MainAxisSize.min,
        children: [
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 5),
            decoration: BoxDecoration(
              color: OvanieColors.greenLight,
              borderRadius: BorderRadius.circular(999),
            ),
            child: const Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                Icon(
                  Icons.verified_rounded,
                  size: 14,
                  color: OvanieColors.greenDark,
                ),
                SizedBox(width: 4),
                Text(
                  'Votre véhicule',
                  style: TextStyle(
                    color: OvanieColors.greenDark,
                    fontSize: 10.5,
                    fontWeight: FontWeight.w900,
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 11),
          Text(
            type,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: const TextStyle(
              color: OvanieColors.text,
              fontSize: 22,
              fontWeight: FontWeight.w900,
              letterSpacing: -.4,
            ),
          ),
          if (makeModel.isNotEmpty) ...[
            const SizedBox(height: 3),
            Text(
              makeModel,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: const TextStyle(
                color: OvanieColors.muted,
                fontSize: 12.5,
                fontWeight: FontWeight.w600,
              ),
            ),
          ],
          const SizedBox(height: 12),
          if (plate.isNotEmpty)
            _VehicleFact(
              icon: Icons.confirmation_number_outlined,
              value: plate,
            ),
          if (color.isNotEmpty) ...[
            const SizedBox(height: 8),
            _VehicleFact(
              icon: Icons.circle,
              value: color,
              iconColor: colorValue,
            ),
          ],
          if (zoneLabel.isNotEmpty) ...[
            const SizedBox(height: 8),
            _VehicleFact(
              icon: Icons.location_on_rounded,
              value: zoneLabel,
            ),
          ],
          if (driver.zoneCount > 2) ...[
            const SizedBox(height: 4),
            Text(
              '${driver.zoneCount} communes d’intervention enregistrées',
              maxLines: 2,
              overflow: TextOverflow.ellipsis,
              style: const TextStyle(
                color: OvanieColors.muted,
                fontSize: 10,
                fontWeight: FontWeight.w600,
              ),
            ),
          ],
        ],
      ),
    );

    return Container(
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(24),
        border: Border.all(color: const Color(0xFFE1EAE5)),
        boxShadow: const [
          BoxShadow(
            color: Color(0x100A3525),
            blurRadius: 20,
            offset: Offset(0, 8),
          ),
        ],
      ),
      clipBehavior: Clip.antiAlias,
      child: LayoutBuilder(
        builder: (context, constraints) {
          final compact = constraints.maxWidth < 390;
          if (compact) {
            return Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                SizedBox(height: 190, child: vehicleVisual()),
                info,
              ],
            );
          }

          return SizedBox(
            height: 238,
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Expanded(flex: 46, child: vehicleVisual()),
                Expanded(
                  flex: 54,
                  child: SingleChildScrollView(
                    physics: const NeverScrollableScrollPhysics(),
                    child: info,
                  ),
                ),
              ],
            ),
          );
        },
      ),
    );
  }
}

IconData _vehicleIcon(String type) {
  final value = type.toLowerCase();
  if (value.contains('moto') || value.contains('scooter')) {
    return Icons.two_wheeler_rounded;
  }
  if (value.contains('tricycle') || value.contains('triporteur')) {
    return Icons.electric_rickshaw_rounded;
  }
  return Icons.local_shipping_rounded;
}

class _VehicleFact extends StatelessWidget {
  const _VehicleFact({required this.icon, required this.value, this.iconColor});
  final IconData icon;
  final String value;
  final Color? iconColor;

  @override
  Widget build(BuildContext context) => Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Padding(
            padding: const EdgeInsets.only(top: 1),
            child: Icon(icon, color: iconColor ?? OvanieColors.greenDark, size: 17),
          ),
          const SizedBox(width: 7),
          Expanded(
            child: Text(
              value,
              maxLines: 2,
              overflow: TextOverflow.ellipsis,
              style: const TextStyle(
                color: OvanieColors.text,
                fontSize: 12.5,
                fontWeight: FontWeight.w800,
                height: 1.25,
              ),
            ),
          ),
        ],
      );
}

class _AvailabilityPanel extends StatelessWidget {
  const _AvailabilityPanel({required this.state, required this.loading, required this.onChanged});

  final DriverAvailabilityState state;
  final bool loading;
  final ValueChanged<bool> onChanged;

  @override
  Widget build(BuildContext context) {
    final inMission = state.status == 'En mission';
    final available = state.status == 'Disponible';
    final title = inMission ? 'Vous êtes en mission' : (available ? 'Vous êtes disponible' : 'Vous êtes indisponible');
    final subtitle = inMission
        ? 'La disponibilité sera modifiable après la mission.'
        : (available ? 'Vous pouvez recevoir de nouvelles missions.' : 'Activez votre disponibilité pour recevoir des missions.');

    return Container(
      padding: const EdgeInsets.fromLTRB(16, 15, 14, 15),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(22),
        border: Border.all(color: available || inMission ? const Color(0xFFBDE7CF) : const Color(0xFFE0E6E2)),
        boxShadow: const [BoxShadow(color: Color(0x0B133B2B), blurRadius: 14, offset: Offset(0, 5))],
      ),
      child: Row(
        children: [
          Container(
            width: 48,
            height: 48,
            decoration: BoxDecoration(
              color: available || inMission ? OvanieColors.greenLight : const Color(0xFFF0F2F1),
              shape: BoxShape.circle,
            ),
            child: Icon(inMission ? Icons.local_shipping_rounded : (available ? Icons.check_rounded : Icons.pause_rounded), color: available || inMission ? OvanieColors.greenDark : const Color(0xFF7E8982)),
          ),
          const SizedBox(width: 13),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(title, style: const TextStyle(color: OvanieColors.text, fontSize: 16, fontWeight: FontWeight.w900)),
                const SizedBox(height: 3),
                Text(subtitle, style: const TextStyle(color: OvanieColors.muted, fontSize: 11.5, height: 1.3)),
              ],
            ),
          ),
          if (loading)
            const SizedBox(width: 32, height: 32, child: CircularProgressIndicator(strokeWidth: 2.4, color: OvanieColors.green))
          else
            Switch.adaptive(
              value: available || inMission,
              onChanged: state.canChange ? onChanged : null,
              activeColor: OvanieColors.green,
            ),
        ],
      ),
    );
  }
}

class _SummaryStrip extends StatelessWidget {
  const _SummaryStrip({required this.summary});
  final DriverDashboardSummary summary;

  @override
  Widget build(BuildContext context) => Container(
        padding: const EdgeInsets.symmetric(vertical: 14),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(22),
          border: Border.all(color: const Color(0xFFE1EAE5)),
        ),
        child: Row(
          children: [
            _SummaryMetric(icon: Icons.inventory_2_outlined, value: summary.missionsToday, label: 'Missions\naujourd’hui', color: OvanieColors.green),
            const _MetricDivider(),
            _SummaryMetric(icon: Icons.check_circle_outline_rounded, value: summary.completedToday, label: 'Terminées', color: const Color(0xFF00A86B)),
            const _MetricDivider(),
            _SummaryMetric(icon: Icons.notifications_none_rounded, value: summary.unreadNotifications, label: 'Notifications', color: const Color(0xFFC18417)),
          ],
        ),
      );
}

class _MetricDivider extends StatelessWidget {
  const _MetricDivider();
  @override
  Widget build(BuildContext context) => Container(width: 1, height: 42, color: const Color(0xFFE6ECE8));
}

class _SummaryMetric extends StatelessWidget {
  const _SummaryMetric({required this.icon, required this.value, required this.label, required this.color});
  final IconData icon;
  final int value;
  final String label;
  final Color color;

  @override
  Widget build(BuildContext context) => Expanded(
        child: Column(
          children: [
            Row(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Icon(icon, color: color, size: 18),
                const SizedBox(width: 6),
                Text('$value', style: const TextStyle(color: OvanieColors.text, fontSize: 22, fontWeight: FontWeight.w900)),
              ],
            ),
            const SizedBox(height: 4),
            Text(label, textAlign: TextAlign.center, style: const TextStyle(color: OvanieColors.muted, fontSize: 10.3, height: 1.15, fontWeight: FontWeight.w600)),
          ],
        ),
      );
}

class _SectionHeader extends StatelessWidget {
  const _SectionHeader({required this.title, this.trailing});
  final String title;
  final String? trailing;

  @override
  Widget build(BuildContext context) => Row(
        children: [
          Expanded(child: Text(title, style: const TextStyle(color: OvanieColors.text, fontSize: 19, fontWeight: FontWeight.w900, letterSpacing: -.3))),
          if ((trailing ?? '').trim().isNotEmpty)
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 5),
              decoration: BoxDecoration(color: OvanieColors.greenLight, borderRadius: BorderRadius.circular(999)),
              child: Text(trailing!, style: const TextStyle(color: OvanieColors.greenDark, fontSize: 10.5, fontWeight: FontWeight.w800)),
            ),
        ],
      );
}

class _PriorityMissionCard extends StatelessWidget {
  const _PriorityMissionCard({required this.mission, required this.canReceiveMissions, this.onTap});
  final DriverMissionSummary? mission;
  final bool canReceiveMissions;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final data = mission;
    if (data == null) {
      return Container(
        padding: const EdgeInsets.all(18),
        decoration: BoxDecoration(
          color: const Color(0xFFF8FBF9),
          borderRadius: BorderRadius.circular(22),
          border: Border.all(color: const Color(0xFFCEE7DA)),
        ),
        child: Row(
          children: [
            Container(
              width: 52,
              height: 52,
              decoration: const BoxDecoration(color: OvanieColors.greenLight, shape: BoxShape.circle),
              child: const Icon(Icons.inventory_2_outlined, color: OvanieColors.greenDark, size: 25),
            ),
            const SizedBox(width: 14),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text('Aucune mission prioritaire', style: TextStyle(color: OvanieColors.text, fontSize: 15, fontWeight: FontWeight.w900)),
                  const SizedBox(height: 4),
                  Text(
                    canReceiveMissions
                        ? 'Vous êtes disponible. Une nouvelle mission apparaîtra ici dès son affectation.'
                        : 'Passez Disponible pour pouvoir recevoir une nouvelle mission.',
                    style: const TextStyle(color: OvanieColors.muted, fontSize: 11.5, height: 1.35),
                  ),
                ],
              ),
            ),
          ],
        ),
      );
    }

    final scheduled = data.pickupScheduledAt ?? data.estimatedDeliveryAt;
    final actionLabel = data.status == 'pending' || data.status == 'assigned' || data.status == 'planned'
        ? 'Accepter la mission'
        : 'Voir la mission';
    return Material(
      color: Colors.transparent,
      borderRadius: BorderRadius.circular(24),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(24),
        child: Container(
          padding: const EdgeInsets.all(17),
          decoration: BoxDecoration(
            gradient: const LinearGradient(colors: [Color(0xFF006B3D), Color(0xFF00A35E)], begin: Alignment.topLeft, end: Alignment.bottomRight),
            borderRadius: BorderRadius.circular(24),
            boxShadow: const [BoxShadow(color: Color(0x2A007342), blurRadius: 18, offset: Offset(0, 8))],
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Container(
                    width: 42,
                    height: 42,
                    decoration: BoxDecoration(color: Colors.white.withValues(alpha: .13), borderRadius: BorderRadius.circular(13)),
                    child: const Icon(Icons.bolt_rounded, color: Colors.white),
                  ),
                  const SizedBox(width: 11),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(data.missionNumber, style: const TextStyle(color: Colors.white, fontSize: 16, fontWeight: FontWeight.w900)),
                        const SizedBox(height: 2),
                        Text(data.statusLabel, style: TextStyle(color: Colors.white.withValues(alpha: .78), fontSize: 11.5, fontWeight: FontWeight.w700)),
                      ],
                    ),
                  ),
                  if (scheduled != null)
                    Text(_formatDateTime(scheduled), style: const TextStyle(color: Colors.white, fontSize: 12, fontWeight: FontWeight.w900)),
                ],
              ),
              const SizedBox(height: 16),
              _MissionInfoLine(icon: Icons.location_on_rounded, text: data.destination ?? data.commune ?? 'Destination à confirmer', light: true),
              const SizedBox(height: 8),
              _MissionInfoLine(icon: Icons.inventory_2_outlined, text: '${data.itemCount} article(s) • ${_weight(data.totalWeightKg)}', light: true),
              if ((data.clientName ?? '').trim().isNotEmpty) ...[
                const SizedBox(height: 8),
                _MissionInfoLine(icon: Icons.person_outline_rounded, text: data.clientName!, light: true),
              ],
              const SizedBox(height: 14),
              Row(
                mainAxisAlignment: MainAxisAlignment.end,
                children: [
                  Text(actionLabel, style: const TextStyle(color: Colors.white, fontSize: 12.5, fontWeight: FontWeight.w800)),
                  const SizedBox(width: 4),
                  const Icon(Icons.arrow_forward_rounded, color: Colors.white, size: 16),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _MissionCompactCard extends StatelessWidget {
  const _MissionCompactCard({required this.mission, required this.accent});
  final DriverMissionSummary mission;
  final Color accent;

  @override
  Widget build(BuildContext context) {
    final scheduled = mission.pickupScheduledAt ?? mission.estimatedDeliveryAt;
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(22), border: Border.all(color: const Color(0xFFE1EAE5))),
      child: Column(
        children: [
          Row(
            children: [
              Container(
                width: 40,
                height: 40,
                decoration: BoxDecoration(color: accent.withValues(alpha: .10), borderRadius: BorderRadius.circular(12)),
                child: Icon(Icons.route_rounded, color: accent, size: 21),
              ),
              const SizedBox(width: 11),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(mission.missionNumber, style: const TextStyle(color: OvanieColors.text, fontSize: 14, fontWeight: FontWeight.w900)),
                    const SizedBox(height: 2),
                    Text(mission.statusLabel, style: TextStyle(color: accent, fontSize: 11, fontWeight: FontWeight.w800)),
                  ],
                ),
              ),
              if (scheduled != null) Text(_formatDateTime(scheduled), style: const TextStyle(color: OvanieColors.muted, fontSize: 11, fontWeight: FontWeight.w700)),
            ],
          ),
          const SizedBox(height: 12),
          _MissionInfoLine(icon: Icons.location_on_outlined, text: mission.destination ?? mission.commune ?? 'Destination à confirmer'),
        ],
      ),
    );
  }
}

class _MissionInfoLine extends StatelessWidget {
  const _MissionInfoLine({required this.icon, required this.text, this.light = false});
  final IconData icon;
  final String text;
  final bool light;

  @override
  Widget build(BuildContext context) => Row(
        children: [
          Icon(icon, size: 17, color: light ? Colors.white.withValues(alpha: .75) : OvanieColors.muted),
          const SizedBox(width: 8),
          Expanded(
            child: Text(
              text,
              maxLines: 2,
              overflow: TextOverflow.ellipsis,
              style: TextStyle(color: light ? Colors.white : OvanieColors.muted, fontSize: 12.3, height: 1.35, fontWeight: light ? FontWeight.w700 : FontWeight.w600),
            ),
          ),
        ],
      );
}

class _NotificationsCard extends StatelessWidget {
  const _NotificationsCard({required this.notifications, required this.unreadCount});
  final List<DriverDashboardNotification> notifications;
  final int unreadCount;

  @override
  Widget build(BuildContext context) => Container(
        decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(22), border: Border.all(color: const Color(0xFFE1EAE5))),
        child: Column(
          children: [
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 14, 16, 12),
              child: Row(
                children: [
                  const Icon(Icons.notifications_none_rounded, color: OvanieColors.text, size: 20),
                  const SizedBox(width: 8),
                  const Expanded(child: Text('Dernières notifications', style: TextStyle(color: OvanieColors.text, fontSize: 15, fontWeight: FontWeight.w900))),
                  if (unreadCount > 0)
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                      decoration: BoxDecoration(color: OvanieColors.greenLight, borderRadius: BorderRadius.circular(999)),
                      child: Text('$unreadCount', style: const TextStyle(color: OvanieColors.greenDark, fontSize: 10.5, fontWeight: FontWeight.w900)),
                    ),
                ],
              ),
            ),
            const Divider(height: 1, color: OvanieColors.border),
            ...notifications.take(3).map((item) => Padding(
                  padding: const EdgeInsets.fromLTRB(16, 12, 16, 12),
                  child: Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Container(
                        width: 34,
                        height: 34,
                        decoration: BoxDecoration(color: item.read ? const Color(0xFFF0F3F1) : OvanieColors.greenLight, shape: BoxShape.circle),
                        child: Icon(item.read ? Icons.notifications_none_rounded : Icons.notifications_active_rounded, size: 17, color: item.read ? OvanieColors.muted : OvanieColors.green),
                      ),
                      const SizedBox(width: 10),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(item.title, style: TextStyle(color: OvanieColors.text, fontSize: 12.5, fontWeight: item.read ? FontWeight.w700 : FontWeight.w900)),
                            if (item.message.trim().isNotEmpty) ...[
                              const SizedBox(height: 3),
                              Text(item.message, maxLines: 2, overflow: TextOverflow.ellipsis, style: const TextStyle(color: OvanieColors.muted, fontSize: 11.3, height: 1.3)),
                            ],
                          ],
                        ),
                      ),
                      if (item.createdAt != null) Text(_relativeTime(item.createdAt!), style: const TextStyle(color: OvanieColors.muted, fontSize: 9.5)),
                    ],
                  ),
                )),
          ],
        ),
      );
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
  if (value.contains('turquoise')) return const Color(0xFF1B9C9A);
  if (value.contains('bleu')) return const Color(0xFF2D63B7);
  if (value.contains('violet')) return const Color(0xFF7250A8);
  if (value.contains('rose') || value.contains('bordeaux')) return const Color(0xFFA53757);
  return null;
}

Color? _parseHexColor(String? value) {
  final input = (value ?? '').replaceAll('#', '').trim();
  if (input.length != 6) return null;
  final parsed = int.tryParse(input, radix: 16);
  return parsed == null ? null : Color(0xFF000000 | parsed);
}

String _formatDateTime(DateTime value) {
  final local = value.toLocal();
  final now = DateTime.now();
  final isToday = local.year == now.year && local.month == now.month && local.day == now.day;
  final hour = local.hour.toString().padLeft(2, '0');
  final minute = local.minute.toString().padLeft(2, '0');
  if (isToday) return '$hour:$minute';
  final day = local.day.toString().padLeft(2, '0');
  final month = local.month.toString().padLeft(2, '0');
  return '$day/$month $hour:$minute';
}

String _relativeTime(DateTime value) {
  final diff = DateTime.now().difference(value.toLocal());
  if (diff.inMinutes < 1) return 'maintenant';
  if (diff.inMinutes < 60) return '${diff.inMinutes} min';
  if (diff.inHours < 24) return '${diff.inHours} h';
  return '${diff.inDays} j';
}

String _weight(double value) {
  if (value <= 0) return 'poids non renseigné';
  if (value == value.roundToDouble()) return '${value.toInt()} kg';
  return '${value.toStringAsFixed(1).replaceAll('.', ',')} kg';
}

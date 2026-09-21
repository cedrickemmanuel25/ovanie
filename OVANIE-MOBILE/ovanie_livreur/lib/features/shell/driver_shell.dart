import 'package:flutter/material.dart';

import '../../app/theme.dart';
import '../../core/location/driver_presence_service.dart';
import '../driver/models/driver_profile.dart';
import '../history/history_screen.dart';
import '../home/driver_home_tab.dart';
import '../missions/missions_screen.dart';
import '../missions/models/driver_mission.dart';
import '../missions/screens/mission_accepted_screen.dart';
import '../missions/screens/mission_collecting_screen.dart';
import '../missions/screens/mission_departure_screen.dart';
import '../missions/screens/mission_detail_screen.dart';
import '../profile/profile_screen.dart';
import '../tracking/tracking_screen.dart';

/// Navigation basse affichée uniquement lorsque `onboarding_status == 'active'`.
class DriverShell extends StatefulWidget {
  const DriverShell({super.key, required this.driver, required this.onRefresh});

  final DriverProfile driver;
  final Future<void> Function() onRefresh;

  @override
  State<DriverShell> createState() => _DriverShellState();
}

enum _DriverTab { home, missions, tracking, history, profile }

class _DriverShellState extends State<DriverShell> with WidgetsBindingObserver {
  _DriverTab _tab = _DriverTab.home;
  final GlobalKey<NavigatorState> _missionsNavigatorKey =
      GlobalKey<NavigatorState>();

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    DriverPresenceService.instance.start();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) {
      DriverPresenceService.instance.refreshNow();
    }
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    DriverPresenceService.instance.stop();
    super.dispose();
  }

  void _openTrackedMission(DriverMissionDetail mission) {
    setState(() => _tab = _DriverTab.missions);
    WidgetsBinding.instance.addPostFrameCallback((_) {
      final navigator = _missionsNavigatorKey.currentState;
      if (navigator == null) return;

      Widget screen;
      if (mission.isToAccept) {
        screen = MissionDetailScreen(
          missionNumber: mission.missionNumber,
          initialMission: mission,
        );
      } else if (mission.isAccepted) {
        screen = MissionAcceptedScreen(
          missionNumber: mission.missionNumber,
          initialMission: mission,
        );
      } else if (mission.isCollecting) {
        screen = MissionCollectingScreen(
          missionNumber: mission.missionNumber,
          initialMission: mission,
        );
      } else if (mission.isLoaded || mission.isInTransit || mission.isArrived) {
        screen = MissionDepartureScreen(
          missionNumber: mission.missionNumber,
          initialMission: mission,
        );
      } else {
        screen = MissionDetailScreen(
          missionNumber: mission.missionNumber,
          initialMission: mission,
        );
      }

      navigator.push(MaterialPageRoute<void>(builder: (_) => screen));
    });
  }

  @override
  Widget build(BuildContext context) {
    final pages = <_DriverTab, Widget>{
      _DriverTab.home:
          DriverHomeTab(driver: widget.driver, onRefresh: widget.onRefresh),
      _DriverTab.missions: MissionsScreen(navigatorKey: _missionsNavigatorKey),
      _DriverTab.tracking: TrackingScreen(onOpenMission: _openTrackedMission),
      _DriverTab.history: const HistoryScreen(),
      _DriverTab.profile:
          ProfileScreen(driver: widget.driver, onRefresh: widget.onRefresh),
    };

    return Scaffold(
      backgroundColor: OvanieColors.background,
      body: IndexedStack(
        index: _DriverTab.values.indexOf(_tab),
        children: _DriverTab.values
            .map((tab) => pages[tab]!)
            .toList(growable: false),
      ),
      bottomNavigationBar: _DriverBottomBar(
        selected: _tab,
        onSelected: (tab) => setState(() => _tab = tab),
      ),
    );
  }
}

class _DriverBottomBar extends StatelessWidget {
  const _DriverBottomBar({required this.selected, required this.onSelected});

  final _DriverTab selected;
  final ValueChanged<_DriverTab> onSelected;

  static const _items = [
    (_DriverTab.home, 'Accueil', Icons.home_outlined),
    (_DriverTab.missions, 'Missions', Icons.list_alt_rounded),
    (_DriverTab.tracking, 'Suivi', Icons.location_on_outlined),
    (_DriverTab.history, 'Historique', Icons.schedule_rounded),
    (_DriverTab.profile, 'Profil', Icons.person_outline_rounded),
  ];

  @override
  Widget build(BuildContext context) {
    return Material(
      color: Colors.white,
      child: Container(
        decoration: const BoxDecoration(
          color: Colors.white,
          border: Border(top: BorderSide(color: OvanieColors.border, width: .7)),
          boxShadow: [
            BoxShadow(
              color: Color(0x0A000000),
              blurRadius: 8,
              offset: Offset(0, -2),
            ),
          ],
        ),
        child: SafeArea(
          top: false,
          child: SizedBox(
            height: 72,
            child: Row(
              children: _items.map((item) {
                final (tab, label, icon) = item;
                final isSelected = tab == selected;
                final color = isSelected ? OvanieColors.green : OvanieColors.muted;
                final filledMission = isSelected && tab == _DriverTab.missions;

                return Expanded(
                  child: InkWell(
                    onTap: () => onSelected(tab),
                    child: Padding(
                      padding: const EdgeInsets.fromLTRB(2, 7, 2, 6),
                      child: Column(
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: [
                          SizedBox(
                            width: 38,
                            height: 30,
                            child: Center(
                              child: AnimatedContainer(
                                duration: const Duration(milliseconds: 180),
                                width: filledMission ? 38 : 30,
                                height: 30,
                                decoration: BoxDecoration(
                                  color: filledMission
                                      ? OvanieColors.green
                                      : Colors.transparent,
                                  borderRadius: BorderRadius.circular(
                                    filledMission ? 6 : 999,
                                  ),
                                ),
                                child: Icon(
                                  isSelected && tab == _DriverTab.tracking
                                      ? Icons.location_on_rounded
                                      : icon,
                                  color: filledMission ? Colors.white : color,
                                  size: isSelected && tab == _DriverTab.tracking
                                      ? 29
                                      : isSelected
                                          ? 25
                                          : 24,
                                ),
                              ),
                            ),
                          ),
                          const SizedBox(height: 2),
                          Text(
                            label,
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: TextStyle(
                              color: color,
                              fontSize: 11.2,
                              fontWeight:
                                  isSelected ? FontWeight.w900 : FontWeight.w600,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
                );
              }).toList(growable: false),
            ),
          ),
        ),
      ),
    );
  }
}

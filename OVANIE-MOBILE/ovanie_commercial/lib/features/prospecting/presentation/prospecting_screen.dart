import 'package:flutter/material.dart';

import '../../../core/api/api_exception.dart';
import '../../../core/theme/ovanie_colors.dart';
import '../../clients/data/clients_service.dart';
import '../../shops/data/shops_service.dart';
import '../../shops/presentation/open_shop_screen.dart';
import '../data/prospecting_service.dart';
import '../models/prospecting_data.dart';

class CommercialProspectingScreen extends StatefulWidget {
  const CommercialProspectingScreen({
    super.key,
    required this.service,
    required this.shopsService,
    required this.clientsService,
    required this.initialUser,
    required this.unreadNotifications,
    required this.onLogout,
  });

  final ProspectingService service;
  final ShopsService shopsService;
  final ClientsService clientsService;
  final Map<String, dynamic> initialUser;
  final int unreadNotifications;
  final Future<void> Function() onLogout;

  @override
  State<CommercialProspectingScreen> createState() => _CommercialProspectingScreenState();
}

class _CommercialProspectingScreenState extends State<CommercialProspectingScreen> {
  ProspectingOverviewData? _overview;
  List<ProspectData> _prospects = [];
  bool _loading = true;
  String? _error;
  int _tab = 0;
  final TextEditingController _search = TextEditingController();

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    _search.dispose();
    super.dispose();
  }

  ProspectingMissionData? get _mission => _overview?.mission;

  Future<void> _load() async {
    if (!mounted) return;
    setState(() {
      _loading = true;
      _error = null;
    });

    try {
      final overview = await widget.service.overview();
      final prospects = overview.mission == null
          ? <ProspectData>[]
          : await widget.service.prospects(missionId: overview.mission!.id);
      if (!mounted) return;
      setState(() {
        _overview = overview;
        _prospects = prospects;
      });
    } on ApiException catch (e) {
      if (mounted) setState(() => _error = e.message);
    } catch (_) {
      if (mounted) setState(() => _error = 'Impossible de charger votre mission de prospection.');
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _startQuarter(MissionQuarterData quarter) async {
    final mission = _mission;
    if (mission == null) return;
    try {
      await widget.service.startQuarter(mission.id, quarter.id);
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('${quarter.name} est maintenant en cours pour toute l’équipe.'),
          behavior: SnackBarBehavior.floating,
        ),
      );
      await _load();
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('$e'), behavior: SnackBarBehavior.floating),
        );
      }
    }
  }

  Future<void> _finishQuarter(MissionQuarterData quarter) async {
    final mission = _mission;
    if (mission == null) return;

    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Clôturer ce quartier ?'),
        content: Text(
          'Confirmez que l’équipe a terminé la prospection de ${quarter.name}. Le quartier sera marqué comme couvert pour tous les commerciaux de la mission.',
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Annuler')),
          FilledButton(onPressed: () => Navigator.pop(context, true), child: const Text('Clôturer')),
        ],
      ),
    );
    if (confirmed != true) return;

    try {
      await widget.service.finishQuarter(mission.id, quarter.id);
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('${quarter.name} est terminé.'), behavior: SnackBarBehavior.floating),
      );
      await _load();
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('$e'), behavior: SnackBarBehavior.floating),
        );
      }
    }
  }

  Future<void> _openShopFromMission(ProspectingMissionData mission, MissionQuarterData quarter) async {
    if (quarter.completed) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Ce quartier est déjà clôturé pour cette mission.'),
          behavior: SnackBarBehavior.floating,
        ),
      );
      return;
    }

    final created = await Navigator.push<bool>(
      context,
      MaterialPageRoute(
        builder: (_) => CommercialOpenShopScreen(
          shopsService: widget.shopsService,
          clientsService: widget.clientsService,
          initialUser: widget.initialUser,
          unreadNotifications: widget.unreadNotifications,
          onLogout: widget.onLogout,
          prospectPrefill: {
            'prospecting_mission_id': mission.id,
            'prospecting_quarter_id': quarter.quarterId,
            'mission_commune_name': mission.commune,
            'mission_quarter_name': quarter.name,
            'commune_id': mission.communeId,
            'quarter_id': quarter.quarterId,
          },
        ),
      ),
    );

    if (created == true && mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Boutique créée et rattachée à la mission ${mission.commune}.'),
          behavior: SnackBarBehavior.floating,
        ),
      );
      await _load();
    }
  }

  Future<void> _chooseQuarterAndOpenShop() async {
    final mission = _mission;
    if (mission == null) return;

    final openQuarters = mission.quarters.where((q) => !q.completed).toList()
      ..sort((a, b) {
        if (a.inProgress == b.inProgress) return a.name.compareTo(b.name);
        return a.inProgress ? -1 : 1;
      });

    if (openQuarters.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Tous les quartiers de la mission sont clôturés.'), behavior: SnackBarBehavior.floating),
      );
      return;
    }

    if (openQuarters.length == 1) {
      await _openShopFromMission(mission, openQuarters.first);
      return;
    }

    final quarter = await showModalBottomSheet<MissionQuarterData>(
      context: context,
      backgroundColor: Colors.white,
      showDragHandle: true,
      isScrollControlled: true,
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(24))),
      builder: (context) => SafeArea(
        top: false,
        child: Padding(
          padding: const EdgeInsets.fromLTRB(18, 0, 18, 18),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Text(
                'Dans quel quartier se trouve la boutique ?',
                style: TextStyle(color: Color(0xFF0B2D59), fontSize: 18, fontWeight: FontWeight.w900),
              ),
              const SizedBox(height: 5),
              Text(
                'Seuls les quartiers affectés à la mission de ${mission.commune} sont disponibles.',
                style: const TextStyle(color: Color(0xFF6E7D91), fontSize: 12, height: 1.4),
              ),
              const SizedBox(height: 14),
              ConstrainedBox(
                constraints: BoxConstraints(maxHeight: MediaQuery.sizeOf(context).height * .55),
                child: ListView.separated(
                  shrinkWrap: true,
                  itemCount: openQuarters.length,
                  separatorBuilder: (_, __) => const Divider(height: 1),
                  itemBuilder: (context, index) {
                    final q = openQuarters[index];
                    return ListTile(
                      contentPadding: const EdgeInsets.symmetric(horizontal: 2, vertical: 3),
                      leading: Container(
                        width: 42,
                        height: 42,
                        decoration: BoxDecoration(
                          color: q.inProgress ? const Color(0xFFE8F2FF) : const Color(0xFFF1F4F8),
                          borderRadius: BorderRadius.circular(12),
                        ),
                        child: Icon(
                          q.inProgress ? Icons.route_rounded : Icons.location_on_outlined,
                          color: q.inProgress ? const Color(0xFF1268C4) : const Color(0xFF65768D),
                        ),
                      ),
                      title: Text(q.name, style: const TextStyle(color: Color(0xFF102F59), fontWeight: FontWeight.w800)),
                      subtitle: Text(q.inProgress ? 'Prospection en cours' : 'Quartier à couvrir'),
                      trailing: const Icon(Icons.chevron_right_rounded),
                      onTap: () => Navigator.pop(context, q),
                    );
                  },
                ),
              ),
            ],
          ),
        ),
      ),
    );

    if (quarter != null && mounted) await _openShopFromMission(mission, quarter);
  }

  Future<void> _visit(ProspectData prospect) async {
    var outcome = 'interested';
    final notes = TextEditingController();
    final ok = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => StatefulBuilder(
        builder: (context, setLocal) => AlertDialog(
          title: Text(prospect.businessName),
          content: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              DropdownButtonFormField<String>(
                value: outcome,
                isExpanded: true,
                dropdownColor: Colors.white,
                borderRadius: BorderRadius.circular(10),
                elevation: 3,
                menuMaxHeight: 320,
                icon: const Icon(Icons.keyboard_arrow_down_rounded, color: Color(0xFF5A7196), size: 20),
                style: const TextStyle(color: Color(0xFF00133A), fontSize: 14),
                decoration: InputDecoration(
                  labelText: 'Résultat de la visite',
                  labelStyle: const TextStyle(color: Color(0xFF274B79), fontSize: 13),
                  isDense: true,
                  filled: true,
                  fillColor: Colors.white,
                  contentPadding: const EdgeInsets.symmetric(horizontal: 13, vertical: 15),
                  border: OutlineInputBorder(borderRadius: BorderRadius.circular(10)),
                ),
                items: const [
                  DropdownMenuItem(value: 'visited', child: Text('Visité')),
                  DropdownMenuItem(value: 'interested', child: Text('Intéressé')),
                  DropdownMenuItem(value: 'follow_up', child: Text('À relancer')),
                  DropdownMenuItem(value: 'registration_in_progress', child: Text('Inscription en cours')),
                  DropdownMenuItem(value: 'unavailable', child: Text('Indisponible')),
                  DropdownMenuItem(value: 'refused', child: Text('Refus')),
                ],
                onChanged: (value) {
                  if (value != null) setLocal(() => outcome = value);
                },
              ),
              const SizedBox(height: 10),
              TextField(
                controller: notes,
                maxLines: 4,
                decoration: const InputDecoration(labelText: 'Compte rendu / prochaine action'),
              ),
            ],
          ),
          actions: [
            TextButton(onPressed: () => Navigator.pop(dialogContext, false), child: const Text('Annuler')),
            FilledButton(onPressed: () => Navigator.pop(dialogContext, true), child: const Text('Enregistrer')),
          ],
        ),
      ),
    );

    try {
      if (ok == true) {
        await widget.service.visit(prospect.id, outcome: outcome, notes: notes.text.trim());
        if (mounted) await _load();
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('$e'), behavior: SnackBarBehavior.floating),
        );
      }
    } finally {
      notes.dispose();
    }
  }

  Future<void> _openShopForProspect(ProspectData prospect) async {
    final created = await Navigator.push<bool>(
      context,
      MaterialPageRoute(
        builder: (_) => CommercialOpenShopScreen(
          shopsService: widget.shopsService,
          clientsService: widget.clientsService,
          initialUser: widget.initialUser,
          unreadNotifications: widget.unreadNotifications,
          onLogout: widget.onLogout,
          prospectPrefill: {
            'id': prospect.id,
            'prospecting_mission_id': prospect.quarterId == null ? null : prospect.missionId,
            'prospecting_quarter_id': prospect.quarterId,
            'mission_commune_name': prospect.commune,
            'mission_quarter_name': prospect.quarter,
            'business_name': prospect.businessName,
            'contact_name': prospect.contactName,
            'phone': prospect.phone,
            'whatsapp': prospect.whatsapp,
            'address': prospect.address,
            'landmark': prospect.landmark,
            'commune_id': prospect.communeId,
            'quarter_id': prospect.quarterId,
            'latitude': prospect.latitude,
            'longitude': prospect.longitude,
          },
        ),
      ),
    );
    if (created == true && mounted) await _load();
  }

  String _formatDate(String value) {
    final date = DateTime.tryParse(value);
    if (date == null) return value;
    String two(int v) => v.toString().padLeft(2, '0');
    return '${two(date.day)}/${two(date.month)}/${date.year}';
  }

  int _daysRemaining(ProspectingMissionData mission) {
    final end = DateTime.tryParse(mission.endsOn);
    if (end == null) return 0;
    final today = DateTime.now();
    final todayOnly = DateTime(today.year, today.month, today.day);
    final endOnly = DateTime(end.year, end.month, end.day);
    return endOnly.difference(todayOnly).inDays + 1;
  }

  String _prospectStatus(String value) => const {
        'to_visit': 'À visiter',
        'visited': 'Visité',
        'interested': 'Intéressé',
        'follow_up': 'À relancer',
        'registration_in_progress': 'Inscription en cours',
        'vendor_created': 'Compte vendeur créé',
        'shop_opened': 'Boutique ouverte',
        'active_shop': 'Boutique active',
        'refused': 'Refus',
        'unavailable': 'Indisponible',
      }[value] ?? value;

  @override
  Widget build(BuildContext context) {
    final mission = _mission;

    return Scaffold(
      backgroundColor: const Color(0xFFF5F7FB),
      appBar: AppBar(
        elevation: 0,
        backgroundColor: const Color(0xFF062451),
        foregroundColor: Colors.white,
        titleSpacing: 4,
        title: const Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text('Mission de prospection', style: TextStyle(fontSize: 18, fontWeight: FontWeight.w800)),
            Text('Acquisition de vendeurs OVANIE', style: TextStyle(fontSize: 10.5, color: Color(0xFFBCD0E9))),
          ],
        ),
        actions: [
          IconButton(
            tooltip: 'Actualiser',
            onPressed: _loading ? null : _load,
            icon: const Icon(Icons.refresh_rounded),
          ),
          const SizedBox(width: 4),
        ],
      ),
      bottomNavigationBar: mission == null
          ? null
          : SafeArea(
              top: false,
              child: Container(
                padding: const EdgeInsets.fromLTRB(16, 10, 16, 12),
                decoration: const BoxDecoration(
                  color: Colors.white,
                  border: Border(top: BorderSide(color: Color(0xFFE2E8F0))),
                  boxShadow: [BoxShadow(color: Color(0x14000000), blurRadius: 18, offset: Offset(0, -4))],
                ),
                child: FilledButton.icon(
                  style: FilledButton.styleFrom(
                    backgroundColor: OvanieColors.orange,
                    foregroundColor: Colors.white,
                    minimumSize: const Size.fromHeight(50),
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(13)),
                  ),
                  onPressed: _loading ? null : _chooseQuarterAndOpenShop,
                  icon: const Icon(Icons.storefront_rounded),
                  label: const Text('Ouvrir une boutique vendeur', style: TextStyle(fontWeight: FontWeight.w800)),
                ),
              ),
            ),
      body: RefreshIndicator(
        onRefresh: _load,
        child: _loading
            ? ListView(
                physics: const AlwaysScrollableScrollPhysics(),
                children: [const SizedBox(height: 230), Center(child: CircularProgressIndicator())],
              )
            : _error != null
                ? ListView(
                    physics: const AlwaysScrollableScrollPhysics(),
                    children: [_errorView(_error!)],
                  )
                : mission == null
                    ? _noMissionView()
                    : _missionView(mission),
      ),
    );
  }

  Widget _missionView(ProspectingMissionData mission) {
    final query = _search.text.trim().toLowerCase();
    final visibleQuarters = mission.quarters.where((q) => query.isEmpty || q.name.toLowerCase().contains(query)).toList();
    final visibleProspects = _prospects.where((p) {
      if (query.isEmpty) return true;
      return '${p.businessName} ${p.category} ${p.contactName} ${p.phone} ${p.quarter}'.toLowerCase().contains(query);
    }).toList();

    final remaining = (_overview!.quartersTotal - _overview!.quartersCompleted).clamp(0, _overview!.quartersTotal);
    final days = _daysRemaining(mission);

    return ListView(
      physics: const AlwaysScrollableScrollPhysics(),
      padding: const EdgeInsets.fromLTRB(14, 14, 14, 18),
      children: [
        _missionHero(mission, days),
        const SizedBox(height: 12),
        LayoutBuilder(
          builder: (context, constraints) {
            final width = (constraints.maxWidth - 10) / 2;
            return Wrap(
              spacing: 10,
              runSpacing: 10,
              children: [
                _metricCard(width, 'Quartiers restants', '$remaining', Icons.map_outlined, const Color(0xFF176FC1)),
                _metricCard(width, 'Boutiques ouvertes', '${_overview!.shopsOpened}${mission.shopTarget == null ? '' : '/${mission.shopTarget}'}', Icons.storefront_rounded, const Color(0xFF168A55)),
                _metricCard(width, 'Vendeurs suivis', '${_overview!.prospects}', Icons.groups_2_outlined, const Color(0xFF7B54C6)),
                _metricCard(width, 'Visites terrain', '${_overview!.visits}', Icons.directions_walk_rounded, const Color(0xFFDE6A12)),
              ],
            );
          },
        ),
        const SizedBox(height: 18),
        Row(
          children: [
            const Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text('Suivi de la mission', style: TextStyle(color: Color(0xFF0D2D56), fontSize: 17, fontWeight: FontWeight.w900)),
                  SizedBox(height: 2),
                  Text('L’avancement est partagé avec toute l’équipe.', style: TextStyle(color: Color(0xFF77869A), fontSize: 11)),
                ],
              ),
            ),
            _progressBadge(mission.progressPercent),
          ],
        ),
        const SizedBox(height: 12),
        Container(
          padding: const EdgeInsets.all(4),
          decoration: BoxDecoration(color: const Color(0xFFE9EEF5), borderRadius: BorderRadius.circular(12)),
          child: Row(
            children: [
              Expanded(child: _tabButton(0, Icons.location_on_outlined, 'Quartiers')),
              Expanded(child: _tabButton(1, Icons.store_outlined, 'Suivi vendeurs')),
            ],
          ),
        ),
        const SizedBox(height: 10),
        TextField(
          controller: _search,
          onChanged: (_) => setState(() {}),
          decoration: InputDecoration(
            prefixIcon: const Icon(Icons.search_rounded, color: Color(0xFF6D7D91)),
            suffixIcon: _search.text.isEmpty
                ? null
                : IconButton(
                    onPressed: () {
                      _search.clear();
                      setState(() {});
                    },
                    icon: const Icon(Icons.close_rounded),
                  ),
            hintText: _tab == 0 ? 'Rechercher un quartier…' : 'Rechercher un vendeur…',
            hintStyle: const TextStyle(color: Color(0xFF94A0B1), fontSize: 12),
            filled: true,
            fillColor: Colors.white,
            contentPadding: const EdgeInsets.symmetric(vertical: 13),
            border: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: Color(0xFFDDE5EF))),
            enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: Color(0xFFDDE5EF))),
            focusedBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: Color(0xFF176FC1), width: 1.3)),
          ),
        ),
        const SizedBox(height: 12),
        if (_tab == 0)
          if (visibleQuarters.isEmpty)
            _emptyCard('Aucun quartier', 'Aucun quartier de la mission ne correspond à votre recherche.')
          else
            ...visibleQuarters.map((q) => _quarterCard(mission, q))
        else if (visibleProspects.isEmpty)
          _emptyCard('Aucun vendeur suivi', 'Les vendeurs et boutiques liés à cette mission apparaîtront ici.')
        else
          ...visibleProspects.map(_prospectCard),
      ],
    );
  }

  Widget _missionHero(ProspectingMissionData mission, int daysRemaining) {
    final teamNames = mission.team.map((e) => e.name).where((e) => e.trim().isNotEmpty).toList();
    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        color: const Color(0xFF082B5A),
        borderRadius: BorderRadius.circular(18),
        boxShadow: const [BoxShadow(color: Color(0x1A082B5A), blurRadius: 20, offset: Offset(0, 8))],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 6),
                decoration: BoxDecoration(color: const Color(0xFF0F427F), borderRadius: BorderRadius.circular(20)),
                child: const Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Icon(Icons.verified_rounded, color: Color(0xFF8CC2FF), size: 14),
                    SizedBox(width: 5),
                    Text('MISSION OVANIE', style: TextStyle(color: Color(0xFFC9E1FA), fontSize: 9, fontWeight: FontWeight.w900, letterSpacing: .6)),
                  ],
                ),
              ),
              const Spacer(),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 6),
                decoration: BoxDecoration(color: const Color(0xFF173E6B), borderRadius: BorderRadius.circular(20)),
                child: Text(
                  daysRemaining > 0 ? '$daysRemaining jour${daysRemaining > 1 ? 's' : ''} restant${daysRemaining > 1 ? 's' : ''}' : 'Dernier jour',
                  style: const TextStyle(color: Colors.white, fontSize: 9.5, fontWeight: FontWeight.w800),
                ),
              ),
            ],
          ),
          const SizedBox(height: 14),
          Text(mission.commune, style: const TextStyle(color: Colors.white, fontSize: 28, fontWeight: FontWeight.w900, letterSpacing: -.5)),
          const SizedBox(height: 4),
          Row(
            children: [
              const Icon(Icons.calendar_month_outlined, color: Color(0xFFAECBEA), size: 16),
              const SizedBox(width: 6),
              Text(
                '${_formatDate(mission.startsOn)} au ${_formatDate(mission.endsOn)}',
                style: const TextStyle(color: Color(0xFFD2E2F3), fontSize: 11.5, fontWeight: FontWeight.w700),
              ),
            ],
          ),
          const SizedBox(height: 14),
          Container(height: 1, color: const Color(0xFF244B78)),
          const SizedBox(height: 13),
          Text(
            mission.instructions.isEmpty
                ? 'Présentez OVANIE aux vendeurs BTP de la commune et accompagnez les vendeurs intéressés jusqu’à l’ouverture complète de leur boutique.'
                : mission.instructions,
            style: const TextStyle(color: Color(0xFFD4E2F1), fontSize: 11.5, height: 1.5),
          ),
          const SizedBox(height: 14),
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Icon(Icons.groups_2_outlined, color: Color(0xFF9CC9F6), size: 18),
              const SizedBox(width: 8),
              Expanded(
                child: Text(
                  teamNames.isEmpty ? 'Équipe commerciale OVANIE' : '${teamNames.length} ${teamNames.length > 1 ? 'commerciaux' : 'commercial'} : ${teamNames.join(', ')}',
                  style: const TextStyle(color: Color(0xFFC6D9ED), fontSize: 10.5, height: 1.4),
                ),
              ),
            ],
          ),
          const SizedBox(height: 14),
          Row(
            children: [
              const Expanded(child: Text('Couverture des quartiers', style: TextStyle(color: Color(0xFFBFD5EB), fontSize: 10.5, fontWeight: FontWeight.w700))),
              Text('${mission.progressPercent}%', style: const TextStyle(color: Colors.white, fontSize: 12, fontWeight: FontWeight.w900)),
            ],
          ),
          const SizedBox(height: 7),
          ClipRRect(
            borderRadius: BorderRadius.circular(20),
            child: LinearProgressIndicator(
              value: (mission.progressPercent / 100).clamp(0.0, 1.0).toDouble(),
              minHeight: 8,
              backgroundColor: const Color(0xFF244A76),
              valueColor: const AlwaysStoppedAnimation<Color>(OvanieColors.orange),
            ),
          ),
        ],
      ),
    );
  }

  Widget _metricCard(double width, String label, String value, IconData icon, Color iconColor) {
    return SizedBox(
      width: width,
      height: 96,
      child: Container(
        padding: const EdgeInsets.all(13),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(14),
          border: Border.all(color: const Color(0xFFE1E7EF)),
        ),
        child: Row(
          children: [
            Container(
              width: 40,
              height: 40,
              decoration: BoxDecoration(color: iconColor.withOpacity(.09), borderRadius: BorderRadius.circular(12)),
              child: Icon(icon, color: iconColor, size: 21),
            ),
            const SizedBox(width: 10),
            Expanded(
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(value, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(color: Color(0xFF102F59), fontSize: 21, fontWeight: FontWeight.w900)),
                  const SizedBox(height: 2),
                  Text(label, maxLines: 2, overflow: TextOverflow.ellipsis, style: const TextStyle(color: Color(0xFF758397), fontSize: 9.5, height: 1.2)),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _progressBadge(int percent) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
      decoration: BoxDecoration(color: const Color(0xFFE9F3FF), borderRadius: BorderRadius.circular(20)),
      child: Text('$percent% couvert', style: const TextStyle(color: Color(0xFF1469C2), fontSize: 10, fontWeight: FontWeight.w900)),
    );
  }

  Widget _tabButton(int value, IconData icon, String label) {
    final selected = _tab == value;
    return InkWell(
      borderRadius: BorderRadius.circular(9),
      onTap: () => setState(() {
        _tab = value;
        _search.clear();
      }),
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 160),
        padding: const EdgeInsets.symmetric(vertical: 10, horizontal: 8),
        decoration: BoxDecoration(
          color: selected ? Colors.white : Colors.transparent,
          borderRadius: BorderRadius.circular(9),
          boxShadow: selected ? const [BoxShadow(color: Color(0x12000000), blurRadius: 8, offset: Offset(0, 2))] : null,
        ),
        child: Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(icon, size: 17, color: selected ? const Color(0xFF0D4F98) : const Color(0xFF76869A)),
            const SizedBox(width: 6),
            Flexible(
              child: Text(
                label,
                overflow: TextOverflow.ellipsis,
                style: TextStyle(color: selected ? const Color(0xFF0D3C73) : const Color(0xFF6E7E92), fontSize: 11, fontWeight: FontWeight.w800),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _quarterCard(ProspectingMissionData mission, MissionQuarterData quarter) {
    final statusLabel = quarter.completed ? 'Terminé' : quarter.inProgress ? 'En cours' : 'À couvrir';
    final statusColor = quarter.completed ? const Color(0xFF12814D) : quarter.inProgress ? const Color(0xFF146BC2) : const Color(0xFF66758A);
    final statusBackground = quarter.completed ? const Color(0xFFEAF8F0) : quarter.inProgress ? const Color(0xFFEAF3FF) : const Color(0xFFF0F3F7);

    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(15),
        border: Border.all(color: const Color(0xFFE0E7F0)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(
                width: 42,
                height: 42,
                decoration: BoxDecoration(color: statusBackground, borderRadius: BorderRadius.circular(12)),
                child: Icon(quarter.completed ? Icons.check_rounded : Icons.location_on_outlined, color: statusColor, size: 21),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(quarter.name, style: const TextStyle(color: Color(0xFF102F59), fontSize: 15.5, fontWeight: FontWeight.w900)),
                    const SizedBox(height: 4),
                    Text(
                      '${quarter.prospectsCount} vendeur${quarter.prospectsCount > 1 ? 's' : ''} suivi${quarter.prospectsCount > 1 ? 's' : ''} · ${quarter.shopsCount} boutique${quarter.shopsCount > 1 ? 's' : ''}',
                      style: const TextStyle(color: Color(0xFF6E7D90), fontSize: 10.5),
                    ),
                  ],
                ),
              ),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 5),
                decoration: BoxDecoration(color: statusBackground, borderRadius: BorderRadius.circular(20)),
                child: Text(statusLabel, style: TextStyle(color: statusColor, fontSize: 9.5, fontWeight: FontWeight.w900)),
              ),
            ],
          ),
          if (quarter.updatedBy != null && quarter.updatedBy!.trim().isNotEmpty) ...[
            const SizedBox(height: 8),
            Text('Dernière mise à jour : ${quarter.updatedBy}', style: const TextStyle(color: Color(0xFF8A96A8), fontSize: 9.5)),
          ],
          const SizedBox(height: 12),
          if (!quarter.completed) ...[
            SizedBox(
              width: double.infinity,
              child: FilledButton.icon(
                style: FilledButton.styleFrom(
                  backgroundColor: OvanieColors.orange,
                  foregroundColor: Colors.white,
                  minimumSize: const Size.fromHeight(44),
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(11)),
                ),
                onPressed: () => _openShopFromMission(mission, quarter),
                icon: const Icon(Icons.storefront_rounded, size: 19),
                label: const Text('Ouvrir une boutique', style: TextStyle(fontWeight: FontWeight.w800)),
              ),
            ),
            const SizedBox(height: 8),
          ],
          Row(
            children: [
              if (quarter.status == 'pending')
                Expanded(
                  child: OutlinedButton.icon(
                    onPressed: () => _startQuarter(quarter),
                    icon: const Icon(Icons.play_arrow_rounded, size: 18),
                    label: const Text('Commencer le quartier'),
                  ),
                )
              else if (quarter.status == 'in_progress')
                Expanded(
                  child: OutlinedButton.icon(
                    style: OutlinedButton.styleFrom(foregroundColor: const Color(0xFF14764B)),
                    onPressed: () => _finishQuarter(quarter),
                    icon: const Icon(Icons.task_alt_rounded, size: 18),
                    label: const Text('Clôturer le quartier'),
                  ),
                )
              else
                const Expanded(
                  child: Row(
                    children: [
                      Icon(Icons.verified_rounded, color: Color(0xFF168A55), size: 18),
                      SizedBox(width: 6),
                      Text('Quartier couvert par l’équipe', style: TextStyle(color: Color(0xFF168A55), fontSize: 10.5, fontWeight: FontWeight.w800)),
                    ],
                  ),
                ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _prospectCard(ProspectData prospect) {
    final opened = prospect.shopId != null;
    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(15), border: Border.all(color: const Color(0xFFE0E7F0))),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(
                width: 42,
                height: 42,
                decoration: BoxDecoration(color: opened ? const Color(0xFFEAF8F0) : const Color(0xFFFFF2E7), borderRadius: BorderRadius.circular(12)),
                child: Icon(opened ? Icons.store_rounded : Icons.storefront_outlined, color: opened ? const Color(0xFF168A55) : OvanieColors.orange),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(prospect.businessName, style: const TextStyle(color: Color(0xFF102F59), fontSize: 15.5, fontWeight: FontWeight.w900)),
                    const SizedBox(height: 3),
                    Text('${prospect.category.isEmpty ? 'Vendeur BTP' : prospect.category} · ${prospect.location}', style: const TextStyle(color: Color(0xFF66758A), fontSize: 10.5)),
                  ],
                ),
              ),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 5),
                decoration: BoxDecoration(color: opened ? const Color(0xFFEAF8F0) : const Color(0xFFFFF2E7), borderRadius: BorderRadius.circular(18)),
                child: Text(_prospectStatus(prospect.status), style: TextStyle(color: opened ? const Color(0xFF12814D) : const Color(0xFFC95E0A), fontSize: 9, fontWeight: FontWeight.w900)),
              ),
            ],
          ),
          if (prospect.phone.isNotEmpty) ...[
            const SizedBox(height: 9),
            Row(children: [const Icon(Icons.phone_outlined, color: Color(0xFF6E7D91), size: 15), const SizedBox(width: 5), Text(prospect.phone, style: const TextStyle(color: Color(0xFF284E7C), fontSize: 10.5))]),
          ],
          if (prospect.discoveredBy.isNotEmpty) ...[
            const SizedBox(height: 4),
            Text('Suivi par ${prospect.discoveredBy}', style: const TextStyle(color: Color(0xFF8A96A8), fontSize: 9.5)),
          ],
          const SizedBox(height: 12),
          if (!opened)
            Row(
              children: [
                Expanded(child: OutlinedButton(onPressed: () => _visit(prospect), child: const Text('Compte rendu'))),
                const SizedBox(width: 8),
                Expanded(
                  child: FilledButton(
                    style: FilledButton.styleFrom(backgroundColor: OvanieColors.orange),
                    onPressed: () => _openShopForProspect(prospect),
                    child: const Text('Ouvrir boutique'),
                  ),
                ),
              ],
            )
          else
            Container(
              width: double.infinity,
              padding: const EdgeInsets.symmetric(vertical: 10, horizontal: 12),
              decoration: BoxDecoration(color: const Color(0xFFF0FAF4), borderRadius: BorderRadius.circular(10)),
              child: Row(
                children: [
                  const Icon(Icons.check_circle_rounded, color: Color(0xFF168A55), size: 18),
                  const SizedBox(width: 7),
                  Expanded(child: Text(prospect.shopName?.trim().isNotEmpty == true ? 'Boutique ouverte : ${prospect.shopName}' : 'Boutique OVANIE ouverte', style: const TextStyle(color: Color(0xFF126941), fontSize: 10.5, fontWeight: FontWeight.w800))),
                ],
              ),
            ),
        ],
      ),
    );
  }

  Widget _noMissionView() {
    final upcoming = _overview?.upcomingMission;
    final overdue = _overview?.overdueMission;
    return ListView(
      physics: const AlwaysScrollableScrollPhysics(),
      padding: const EdgeInsets.fromLTRB(16, 28, 16, 80),
      children: [
        Container(
          padding: const EdgeInsets.all(22),
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(18),
            border: Border.all(color: overdue == null ? const Color(0xFFDDE5EF) : const Color(0xFFF5C48C)),
          ),
          child: Column(
            children: [
              Container(
                width: 62,
                height: 62,
                decoration: BoxDecoration(color: overdue == null ? const Color(0xFFEAF3FF) : const Color(0xFFFFF2E5), shape: BoxShape.circle),
                child: Icon(overdue == null ? Icons.assignment_outlined : Icons.schedule_rounded, color: overdue == null ? const Color(0xFF176FC1) : OvanieColors.orange, size: 29),
              ),
              const SizedBox(height: 14),
              Text(
                overdue == null ? 'Aucune mission active aujourd’hui' : 'Votre dernière mission est arrivée à échéance',
                textAlign: TextAlign.center,
                style: const TextStyle(color: Color(0xFF102F59), fontSize: 18, fontWeight: FontWeight.w900),
              ),
              const SizedBox(height: 8),
              Text(
                overdue == null
                    ? 'La commune et les quartiers sont affectés par OVANIE. Lorsqu’une mission vous est attribuée, elle apparaît automatiquement ici.'
                    : 'La mission de ${overdue.commune} s’est terminée le ${_formatDate(overdue.endsOn)}. OVANIE doit la prolonger ou vous affecter une nouvelle commune.',
                textAlign: TextAlign.center,
                style: const TextStyle(color: Color(0xFF6E7C90), height: 1.5, fontSize: 12),
              ),
            ],
          ),
        ),
        if (upcoming != null) ...[
          const SizedBox(height: 14),
          Container(
            padding: const EdgeInsets.all(16),
            decoration: BoxDecoration(color: const Color(0xFF082B5A), borderRadius: BorderRadius.circular(16)),
            child: Row(
              children: [
                Container(width: 42, height: 42, decoration: BoxDecoration(color: const Color(0xFF143F72), borderRadius: BorderRadius.circular(12)), child: const Icon(Icons.calendar_month_outlined, color: OvanieColors.orange)),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Text('PROCHAINE MISSION', style: TextStyle(color: Color(0xFF8FB8E6), fontSize: 9, fontWeight: FontWeight.w900, letterSpacing: .6)),
                      const SizedBox(height: 4),
                      Text(upcoming.commune, style: const TextStyle(color: Colors.white, fontSize: 17, fontWeight: FontWeight.w900)),
                      const SizedBox(height: 3),
                      Text('${_formatDate(upcoming.startsOn)} au ${_formatDate(upcoming.endsOn)} · équipe de ${upcoming.teamCount}', style: const TextStyle(color: Color(0xFFD1E0F1), fontSize: 10.5)),
                    ],
                  ),
                ),
              ],
            ),
          ),
        ],
      ],
    );
  }

  Widget _errorView(String message) => Padding(
        padding: const EdgeInsets.all(36),
        child: Column(
          children: [
            const Icon(Icons.cloud_off_rounded, color: Color(0xFF708198), size: 38),
            const SizedBox(height: 12),
            Text(message, textAlign: TextAlign.center, style: const TextStyle(color: Color(0xFF52647C))),
            TextButton(onPressed: _load, child: const Text('Réessayer')),
          ],
        ),
      );

  Widget _emptyCard(String title, String subtitle) => Container(
        padding: const EdgeInsets.all(26),
        decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(14), border: Border.all(color: const Color(0xFFE0E7F0))),
        child: Column(
          children: [
            const Icon(Icons.storefront_outlined, color: Color(0xFF7D8CA0), size: 32),
            const SizedBox(height: 8),
            Text(title, style: const TextStyle(color: Color(0xFF102F59), fontWeight: FontWeight.w800)),
            const SizedBox(height: 4),
            Text(subtitle, textAlign: TextAlign.center, style: const TextStyle(color: Color(0xFF748196), fontSize: 11)),
          ],
        ),
      );
}

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../../../app/theme.dart';
import '../../../core/network/api_client.dart';
import '../data/mission_repository.dart';
import '../models/driver_mission.dart';
import '../widgets/mission_dialogs.dart';
import '../widgets/mission_sections.dart';
import '../widgets/mission_ui.dart';
import 'mission_departure_screen.dart';

class MissionCollectingScreen extends StatefulWidget {
  const MissionCollectingScreen({
    super.key,
    required this.missionNumber,
    this.initialMission,
  });

  final String missionNumber;
  final DriverMissionDetail? initialMission;

  @override
  State<MissionCollectingScreen> createState() => _MissionCollectingScreenState();
}

class _MissionCollectingScreenState extends State<MissionCollectingScreen> {
  DriverMissionDetail? _mission;
  bool _loading = false;
  bool _actionBusy = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    _mission = widget.initialMission;
    _load(showSpinner: _mission == null);
  }

  Future<void> _load({bool showSpinner = true}) async {
    if (showSpinner && mounted) setState(() => _loading = true);
    try {
      final mission = await MissionRepository.instance.fetchMission(widget.missionNumber);
      if (!mounted) return;
      setState(() {
        _mission = mission;
        _error = null;
      });
    } catch (error) {
      if (!mounted) return;
      setState(() => _error = ApiClient.friendlyError(error));
    } finally {
      if (mounted && showSpinner) setState(() => _loading = false);
    }
  }

  Future<void> _advanceCurrentPickup() async {
    final mission = _mission;
    final stop = mission?.currentPickupStop;
    if (mission == null || stop == null || _actionBusy) return;

    String action;
    _PickupVerificationDraft? verification;
    _PickupHandoverDraft? handover;

    if (!stop.arrived) {
      action = 'arrived';
    } else if (!stop.verified) {
      verification = await _showVerificationDialog(stop);
      if (!mounted || verification == null) return;
      action = 'verified';
    } else if (!stop.completed) {
      handover = await _showHandoverDialog(stop);
      if (!mounted || handover == null) return;
      action = 'loaded';
    } else {
      return;
    }

    setState(() => _actionBusy = true);
    try {
      final updated = await MissionRepository.instance.updatePickupStep(
        mission.missionNumber,
        stop.id,
        action: action,
        itemsChecked: verification?.itemsChecked,
        quantitiesChecked: verification?.quantitiesChecked,
        conditionChecked: verification?.conditionChecked,
        handoverConfirmed: handover?.confirmed,
        pickupCode: handover?.code,
        notes: verification?.notes ?? handover?.notes,
      );

      if (!mounted) return;

      if (action == 'loaded' && updated.allPickupsCompleted) {
        final loaded = await MissionRepository.instance.updateStage(
          mission.missionNumber,
          'loaded',
        );
        if (!mounted) return;
        await Navigator.of(context).pushReplacement(
          MaterialPageRoute<void>(
            builder: (_) => MissionDepartureScreen(
              missionNumber: mission.missionNumber,
              initialMission: loaded,
            ),
          ),
        );
        return;
      }

      setState(() => _mission = updated);
      final message = switch (action) {
        'arrived' => 'Arrivée confirmée. Vérifiez maintenant les articles avec le vendeur.',
        'verified' => 'Vérification enregistrée. Procédez au chargement puis demandez le code de remise.',
        'loaded' => 'Remise confirmée. Passez au point de collecte suivant.',
        _ => 'Étape enregistrée.',
      };
      showMissionSnack(context, message);
    } catch (error) {
      if (mounted) showMissionSnack(context, ApiClient.friendlyError(error), error: true);
    } finally {
      if (mounted) setState(() => _actionBusy = false);
    }
  }

  Future<void> _reportProblem() async {
    final draft = await showMissionIncidentDialog(context);
    if (!mounted || draft == null) return;
    setState(() => _actionBusy = true);
    try {
      await MissionRepository.instance.reportIncident(
        widget.missionNumber,
        incidentType: draft.type,
        description: draft.description,
      );
      if (!mounted) return;
      showMissionSnack(context, 'Problème transmis à OVANIE Logistics.');
      await _load(showSpinner: false);
    } catch (error) {
      if (mounted) showMissionSnack(context, ApiClient.friendlyError(error), error: true);
    } finally {
      if (mounted) setState(() => _actionBusy = false);
    }
  }

  Future<void> _navigateTo(DriverMissionPickupStop stop) async {
    final coordinatesAvailable = stop.latitude != null && stop.longitude != null;
    if (coordinatesAvailable) {
      await Clipboard.setData(ClipboardData(text: '${stop.latitude},${stop.longitude}'));
      if (mounted) {
        showMissionSnack(context, 'Coordonnées du point copiées pour votre application GPS.');
      }
      return;
    }
    await Clipboard.setData(ClipboardData(text: stop.address));
    if (mounted) {
      showMissionSnack(context, 'Adresse du point copiée pour votre application GPS.');
    }
  }

  Future<_PickupVerificationDraft?> _showVerificationDialog(
    DriverMissionPickupStop stop,
  ) async {
    var itemsChecked = false;
    var quantitiesChecked = false;
    var conditionChecked = false;
    final notes = TextEditingController();

    final result = await showDialog<_PickupVerificationDraft>(
      context: context,
      barrierDismissible: false,
      builder: (dialogContext) => StatefulBuilder(
        builder: (context, setModalState) {
          final valid = itemsChecked && quantitiesChecked && conditionChecked;
          return AlertDialog(
            title: const Text('Vérifier les articles'),
            content: SingleChildScrollView(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    '${stop.itemCount} article${stop.itemCount > 1 ? 's' : ''} à contrôler avant chargement.',
                    style: const TextStyle(color: MissionPalette.slate, height: 1.35),
                  ),
                  if (stop.itemsInline.isNotEmpty) ...[
                    const SizedBox(height: 8),
                    Text(
                      stop.itemsInline,
                      style: const TextStyle(
                        color: MissionPalette.navy,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ],
                  const SizedBox(height: 10),
                  CheckboxListTile(
                    contentPadding: EdgeInsets.zero,
                    value: itemsChecked,
                    title: const Text('Produits conformes à la commande'),
                    onChanged: (value) => setModalState(() => itemsChecked = value == true),
                  ),
                  CheckboxListTile(
                    contentPadding: EdgeInsets.zero,
                    value: quantitiesChecked,
                    title: const Text('Quantités vérifiées'),
                    onChanged: (value) => setModalState(() => quantitiesChecked = value == true),
                  ),
                  CheckboxListTile(
                    contentPadding: EdgeInsets.zero,
                    value: conditionChecked,
                    title: const Text('État des produits vérifié'),
                    onChanged: (value) => setModalState(() => conditionChecked = value == true),
                  ),
                  const SizedBox(height: 8),
                  TextField(
                    controller: notes,
                    maxLines: 3,
                    maxLength: 500,
                    decoration: const InputDecoration(
                      labelText: 'Observation (facultatif)',
                      hintText: 'Ex. emballage légèrement abîmé, quantité contrôlée…',
                      border: OutlineInputBorder(),
                    ),
                  ),
                ],
              ),
            ),
            actions: [
              TextButton(
                onPressed: () => Navigator.of(dialogContext).pop(),
                child: const Text('Annuler'),
              ),
              FilledButton(
                onPressed: valid
                    ? () => Navigator.of(dialogContext).pop(
                          _PickupVerificationDraft(
                            itemsChecked: itemsChecked,
                            quantitiesChecked: quantitiesChecked,
                            conditionChecked: conditionChecked,
                            notes: notes.text.trim(),
                          ),
                        )
                    : null,
                child: const Text('Valider la vérification'),
              ),
            ],
          );
        },
      ),
    );
    notes.dispose();
    return result;
  }

  Future<_PickupHandoverDraft?> _showHandoverDialog(
    DriverMissionPickupStop stop,
  ) async {
    final code = TextEditingController();
    final notes = TextEditingController();
    var confirmed = false;

    final result = await showDialog<_PickupHandoverDraft>(
      context: context,
      barrierDismissible: false,
      builder: (dialogContext) => StatefulBuilder(
        builder: (context, setModalState) {
          final valid = confirmed && code.text.trim().length == 6;
          return AlertDialog(
            title: const Text('Confirmer la remise vendeur → livreur'),
            content: SingleChildScrollView(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text(
                    'Après chargement complet, demandez au vendeur le code de remise à 6 chiffres affiché dans son espace OVANIE.',
                    style: TextStyle(color: MissionPalette.slate, height: 1.4),
                  ),
                  const SizedBox(height: 14),
                  TextField(
                    controller: code,
                    keyboardType: TextInputType.number,
                    inputFormatters: [FilteringTextInputFormatter.digitsOnly],
                    maxLength: 6,
                    textAlign: TextAlign.center,
                    onChanged: (_) => setModalState(() {}),
                    style: const TextStyle(
                      color: MissionPalette.navy,
                      fontSize: 25,
                      fontWeight: FontWeight.w900,
                      letterSpacing: 8,
                    ),
                    decoration: const InputDecoration(
                      counterText: '',
                      labelText: 'Code de remise',
                      hintText: '000000',
                      border: OutlineInputBorder(),
                    ),
                  ),
                  const SizedBox(height: 8),
                  CheckboxListTile(
                    contentPadding: EdgeInsets.zero,
                    value: confirmed,
                    title: const Text('Je confirme que les produits ont été chargés et remis par le vendeur.'),
                    onChanged: (value) => setModalState(() => confirmed = value == true),
                  ),
                  const SizedBox(height: 8),
                  TextField(
                    controller: notes,
                    maxLines: 2,
                    maxLength: 500,
                    decoration: const InputDecoration(
                      labelText: 'Observation (facultatif)',
                      border: OutlineInputBorder(),
                    ),
                  ),
                ],
              ),
            ),
            actions: [
              TextButton(
                onPressed: () => Navigator.of(dialogContext).pop(),
                child: const Text('Annuler'),
              ),
              FilledButton(
                onPressed: valid
                    ? () => Navigator.of(dialogContext).pop(
                          _PickupHandoverDraft(
                            confirmed: confirmed,
                            code: code.text.trim(),
                            notes: notes.text.trim(),
                          ),
                        )
                    : null,
                child: const Text('Confirmer la remise'),
              ),
            ],
          );
        },
      ),
    );
    code.dispose();
    notes.dispose();
    return result;
  }

  @override
  Widget build(BuildContext context) {
    final mission = _mission;
    final current = mission?.currentPickupStop;
    final total = mission?.pickupStops.length ?? 0;
    final completed = mission?.pickupCompletedCount ?? 0;
    final progress = total == 0 ? 0 : ((completed / total) * 100).round();

    final actionLabel = current == null
        ? 'Collectes terminées'
        : !current.arrived
            ? 'Je suis arrivé chez le vendeur'
            : !current.verified
                ? 'Vérifier les articles'
                : 'Confirmer chargement et remise';

    return Scaffold(
      backgroundColor: MissionPalette.mint,
      body: Column(
        children: [
          MissionPageHeader(
            title: 'Collectes en cours',
            subtitle: 'Vendeur → livreur',
            showBack: true,
            onBack: () => Navigator.of(context).pop(),
            trailing: const MissionStatusPill(
              label: 'Collecte',
              icon: Icons.inventory_2_rounded,
              kind: MissionStatusKind.success,
            ),
          ),
          Expanded(
            child: RefreshIndicator(
              color: OvanieColors.green,
              onRefresh: () => _load(showSpinner: false),
              child: ListView(
                physics: const AlwaysScrollableScrollPhysics(),
                padding: const EdgeInsets.fromLTRB(16, 14, 16, 18),
                children: [
                  if (_loading && mission == null)
                    const SizedBox(
                      height: 390,
                      child: Center(child: CircularProgressIndicator(color: OvanieColors.green)),
                    )
                  else if (_error != null && mission == null)
                    MissionSurfaceCard(
                      child: Column(
                        children: [
                          Text(
                            _error!,
                            textAlign: TextAlign.center,
                            style: const TextStyle(color: MissionPalette.slate, height: 1.4),
                          ),
                          const SizedBox(height: 14),
                          MissionPrimaryButton(label: 'Réessayer', onPressed: () => _load()),
                        ],
                      ),
                    )
                  else if (mission != null) ...[
                    MissionOverviewBlock(
                      mission: mission,
                      progressTitle: 'Progression des collectes',
                      progressValue: progress,
                      progressTrailingLabel: '$completed collecte${completed > 1 ? 's' : ''} sur $total terminée${completed > 1 ? 's' : ''}',
                      message: 'À chaque vendeur : confirmez votre arrivée, vérifiez les articles, chargez puis validez la remise avec le code du vendeur.',
                    ),
                    if (current != null) ...[
                      if (mission.incidentType != null) ...[
                      const SizedBox(height: 12),
                      MissionTintMessage(
                        text: 'Incident en cours : ${incidentTypeLabel(mission.incidentType)}${(mission.incidentDescription ?? '').trim().isNotEmpty ? ' — ${mission.incidentDescription}' : ''}. OVANIE Logistics suit le dossier.',
                        warning: true,
                        icon: Icons.warning_amber_rounded,
                      ),
                    ],
                    const SizedBox(height: 12),
                      _PickupWorkflowCard(stop: current),
                    ],
                    const SizedBox(height: 12),
                    MissionCollectingStops(
                      stops: mission.pickupStops,
                      onNavigate: _navigateTo,
                    ),
                    const SizedBox(height: 12),
                    MissionItineraryStrip(mission: mission),
                    const SizedBox(height: 12),
                    const MissionTintMessage(
                      text: 'Le code de remise appartient au vendeur. Ne validez jamais une collecte avant d’avoir physiquement vérifié et chargé les produits.',
                      warning: true,
                      icon: Icons.verified_user_rounded,
                    ),
                  ],
                ],
              ),
            ),
          ),
          if (mission != null)
            Container(
              padding: const EdgeInsets.fromLTRB(16, 10, 16, 12),
              decoration: const BoxDecoration(
                color: MissionPalette.mint,
                border: Border(top: BorderSide(color: MissionPalette.cardBorder)),
              ),
              child: SafeArea(
                top: false,
                child: Row(
                  children: [
                    Expanded(
                      child: MissionOutlineButton(
                        label: 'Signaler un problème',
                        loading: _actionBusy,
                        onPressed: _reportProblem,
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: MissionPrimaryButton(
                        label: actionLabel,
                        loading: _actionBusy,
                        enabled: current != null,
                        onPressed: _advanceCurrentPickup,
                      ),
                    ),
                  ],
                ),
              ),
            ),
        ],
      ),
    );
  }
}

class _PickupWorkflowCard extends StatelessWidget {
  const _PickupWorkflowCard({required this.stop});

  final DriverMissionPickupStop stop;

  @override
  Widget build(BuildContext context) {
    return MissionSurfaceCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          const MissionSectionTitle('Point de collecte actuel'),
          const SizedBox(height: 6),
          Text(
            stop.locationLabel,
            style: const TextStyle(
              color: MissionPalette.navy,
              fontSize: 14,
              fontWeight: FontWeight.w800,
            ),
          ),
          const SizedBox(height: 4),
          Text(
            stop.itemsInline.isEmpty
                ? '${stop.itemCount} article${stop.itemCount > 1 ? 's' : ''}'
                : stop.itemsInline,
            style: const TextStyle(color: MissionPalette.slate, fontSize: 12.3, height: 1.35),
          ),
          const SizedBox(height: 14),
          _WorkflowLine(
            title: '1. Arrivée chez le vendeur',
            done: stop.arrived,
            active: !stop.arrived,
          ),
          _WorkflowLine(
            title: '2. Vérification produits / quantités / état',
            done: stop.verified,
            active: stop.arrived && !stop.verified,
          ),
          _WorkflowLine(
            title: '3. Chargement + code de remise vendeur',
            done: stop.completed,
            active: stop.verified && !stop.completed,
            last: true,
          ),
        ],
      ),
    );
  }
}

class _WorkflowLine extends StatelessWidget {
  const _WorkflowLine({
    required this.title,
    required this.done,
    required this.active,
    this.last = false,
  });

  final String title;
  final bool done;
  final bool active;
  final bool last;

  @override
  Widget build(BuildContext context) {
    final icon = done ? Icons.check_circle_rounded : active ? Icons.radio_button_checked_rounded : Icons.radio_button_off_rounded;
    final color = done || active ? OvanieColors.green : MissionPalette.slate;
    return Padding(
      padding: EdgeInsets.only(bottom: last ? 0 : 11),
      child: Row(
        children: [
          Icon(icon, color: color, size: 21),
          const SizedBox(width: 10),
          Expanded(
            child: Text(
              title,
              style: TextStyle(
                color: done || active ? MissionPalette.navy : MissionPalette.slate,
                fontSize: 12.5,
                fontWeight: active ? FontWeight.w800 : FontWeight.w600,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _PickupVerificationDraft {
  const _PickupVerificationDraft({
    required this.itemsChecked,
    required this.quantitiesChecked,
    required this.conditionChecked,
    required this.notes,
  });

  final bool itemsChecked;
  final bool quantitiesChecked;
  final bool conditionChecked;
  final String notes;
}

class _PickupHandoverDraft {
  const _PickupHandoverDraft({
    required this.confirmed,
    required this.code,
    required this.notes,
  });

  final bool confirmed;
  final String code;
  final String notes;
}

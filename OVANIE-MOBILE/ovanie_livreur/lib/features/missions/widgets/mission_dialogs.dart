import 'package:flutter/material.dart';

import '../../../app/theme.dart';
import 'mission_ui.dart';

Future<String?> showMissionRejectDialog(BuildContext context) async {
  final controller = TextEditingController();
  final result = await showModalBottomSheet<String>(
    context: context,
    isScrollControlled: true,
    backgroundColor: Colors.white,
    shape: const RoundedRectangleBorder(
      borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
    ),
    builder: (context) {
      return Padding(
        padding: EdgeInsets.fromLTRB(
          20,
          20,
          20,
          20 + MediaQuery.viewInsetsOf(context).bottom,
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            const Text(
              'Refuser la mission',
              style: TextStyle(
                color: MissionPalette.navy,
                fontSize: 21,
                fontWeight: FontWeight.w900,
              ),
            ),
            const SizedBox(height: 7),
            const Text(
              'Vous pouvez préciser la raison. La mission sera renvoyée à OVANIE Logistics.',
              style: TextStyle(
                color: MissionPalette.slate,
                fontSize: 13,
                height: 1.4,
                fontWeight: FontWeight.w500,
              ),
            ),
            const SizedBox(height: 15),
            TextField(
              controller: controller,
              minLines: 3,
              maxLines: 5,
              maxLength: 500,
              decoration: const InputDecoration(
                hintText: 'Raison du refus (facultatif)',
              ),
            ),
            const SizedBox(height: 6),
            Row(
              children: [
                Expanded(
                  child: MissionOutlineButton(
                    label: 'Annuler',
                    onPressed: () => Navigator.pop(context),
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: SizedBox(
                    height: 51,
                    child: FilledButton(
                      onPressed: () => Navigator.pop(context, controller.text.trim()),
                      style: FilledButton.styleFrom(
                        backgroundColor: MissionPalette.danger,
                        foregroundColor: Colors.white,
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                      ),
                      child: const Text(
                        'Confirmer le refus',
                        textAlign: TextAlign.center,
                        style: TextStyle(fontWeight: FontWeight.w900),
                      ),
                    ),
                  ),
                ),
              ],
            ),
          ],
        ),
      );
    },
  );
  controller.dispose();
  return result;
}

class MissionIncidentDraft {
  const MissionIncidentDraft({required this.type, this.description});

  final String type;
  final String? description;
}

Future<MissionIncidentDraft?> showMissionIncidentDialog(BuildContext context) async {
  const types = <String, String>{
    'traffic_jam': 'Circulation / embouteillage',
    'client_absent': 'Client absent',
    'address_issue': 'Adresse incorrecte ou introuvable',
    'vehicle_breakdown': 'Panne du véhicule',
    'accident': 'Accident',
    'product_damaged': 'Produit endommagé',
    'access_impossible': 'Accès impossible',
    'other': 'Autre problème',
  };

  var selected = types.keys.first;
  final controller = TextEditingController();
  final result = await showModalBottomSheet<MissionIncidentDraft>(
    context: context,
    isScrollControlled: true,
    backgroundColor: Colors.white,
    shape: const RoundedRectangleBorder(
      borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
    ),
    builder: (context) {
      return StatefulBuilder(
        builder: (context, setSheetState) {
          return Padding(
            padding: EdgeInsets.fromLTRB(
              20,
              20,
              20,
              20 + MediaQuery.viewInsetsOf(context).bottom,
            ),
            child: SingleChildScrollView(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Text(
                    'Signaler un problème',
                    style: TextStyle(
                      color: MissionPalette.navy,
                      fontSize: 21,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                  const SizedBox(height: 6),
                  const Text(
                    'Le signalement est transmis au centre logistique OVANIE avec la mission concernée.',
                    style: TextStyle(
                      color: MissionPalette.slate,
                      fontSize: 13,
                      height: 1.4,
                    ),
                  ),
                  const SizedBox(height: 15),
                  DropdownButtonFormField<String>(
                    value: selected,
                    decoration: const InputDecoration(labelText: 'Type de problème'),
                    items: types.entries
                        .map((entry) => DropdownMenuItem<String>(
                              value: entry.key,
                              child: Text(entry.value),
                            ))
                        .toList(growable: false),
                    onChanged: (value) {
                      if (value != null) setSheetState(() => selected = value);
                    },
                  ),
                  const SizedBox(height: 12),
                  TextField(
                    controller: controller,
                    minLines: 3,
                    maxLines: 5,
                    maxLength: 1500,
                    decoration: const InputDecoration(
                      labelText: 'Description',
                      hintText: 'Expliquez brièvement le problème rencontré.',
                    ),
                  ),
                  const SizedBox(height: 5),
                  Row(
                    children: [
                      Expanded(
                        child: MissionOutlineButton(
                          label: 'Annuler',
                          onPressed: () => Navigator.pop(context),
                        ),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: MissionPrimaryButton(
                          label: 'Envoyer',
                          onPressed: () => Navigator.pop(
                            context,
                            MissionIncidentDraft(
                              type: selected,
                              description: controller.text.trim().isEmpty
                                  ? null
                                  : controller.text.trim(),
                            ),
                          ),
                        ),
                      ),
                    ],
                  ),
                ],
              ),
            ),
          );
        },
      );
    },
  );
  controller.dispose();
  return result;
}

void showMissionSnack(BuildContext context, String message, {bool error = false}) {
  ScaffoldMessenger.of(context)
    ..hideCurrentSnackBar()
    ..showSnackBar(
      SnackBar(
        content: Text(message),
        backgroundColor: error ? MissionPalette.danger : OvanieColors.greenDark,
        behavior: SnackBarBehavior.floating,
      ),
    );
}

import 'package:flutter/material.dart';

import '../../app/theme.dart';
import '../../shared/widgets/ovanie_widgets.dart';
import '../../shared/widgets/driver_selection_card.dart';
import 'onboarding_data.dart';
import 'vehicle_screen.dart';

class AvailabilityScreen extends StatefulWidget {
  const AvailabilityScreen({super.key, required this.data});

  final OnboardingData data;

  @override
  State<AvailabilityScreen> createState() => _AvailabilityScreenState();
}

class _AvailabilityScreenState extends State<AvailabilityScreen> {
  String? _error;

  void _toggle(String day) {
    setState(() {
      _error = null;
      if (widget.data.availabilities.contains(day)) {
        widget.data.availabilities.remove(day);
      } else {
        widget.data.availabilities.add(day);
      }
    });
  }

  void _continue() {
    if (widget.data.availabilities.isEmpty) {
      setState(
        () => _error = 'Sélectionnez au moins un jour de disponibilité.',
      );
      return;
    }
    Navigator.of(
      context,
    ).push(MaterialPageRoute(builder: (_) => VehicleScreen(data: widget.data)));
  }

  @override
  Widget build(BuildContext context) {
    return OvanieHeroScaffold(
      heroHeightFactor: 0.2,
      showBack: true,
      appBarTitle: 'Créer mon compte livreur',
      child: OvanieWizardBody(
        content: [
          const OvanieStepHeader(
            step: 3,
            total: 5,
            title: 'Vos jours de disponibilité',
          ),
          const SizedBox(height: 8),
          const Text(
            'Sélectionnez les jours où vous êtes disponible pour effectuer des livraisons.',
            style: TextStyle(
              color: OvanieColors.muted,
              fontSize: 13.5,
              height: 1.4,
            ),
          ),
          const SizedBox(height: 16),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              for (final preset in [
                ('En semaine', kWeekDays.take(5).toList()),
                ('Week-end', kWeekDays.skip(5).toList()),
                ('Tous les jours', kWeekDays),
              ])
                OvanieChoiceChip(
                  label: preset.$1,
                  selected:
                      widget.data.availabilities.length == preset.$2.length &&
                      preset.$2.every(widget.data.availabilities.contains),
                  onTap: () => setState(() {
                    _error = null;
                    widget.data.availabilities
                      ..clear()
                      ..addAll(preset.$2);
                  }),
                ),
            ],
          ),
          const SizedBox(height: 20),
          LayoutBuilder(
            builder: (context, constraints) {
              final columns = MediaQuery.textScalerOf(context).scale(13) > 18
                  ? 1
                  : 2;
              final width =
                  (constraints.maxWidth - (columns - 1) * 10) / columns;
              return Wrap(
                spacing: 10,
                runSpacing: 10,
                children: [
                  for (final day in kWeekDays)
                    SizedBox(
                      width: width,
                      child: DriverSelectionCard(
                        title: day,
                        icon: Icons.calendar_today_outlined,
                        compact: true,
                        selected: widget.data.availabilities.contains(day),
                        subtitle: widget.data.availabilities.contains(day)
                            ? 'Disponible'
                            : 'Non disponible',
                        onTap: () => _toggle(day),
                      ),
                    ),
                ],
              );
            },
          ),
          const SizedBox(height: 14),
          Row(
            children: [
              Expanded(
                child: Text(
                  '${widget.data.availabilities.length} / 7 jours',
                  style: const TextStyle(
                    color: OvanieColors.greenDark,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ),
              TextButton(
                onPressed: widget.data.availabilities.isEmpty
                    ? null
                    : () => setState(() {
                        widget.data.availabilities.clear();
                        _error = null;
                      }),
                child: const Text('Effacer'),
              ),
            ],
          ),
        ],
        footer: [
          if (_error != null) OvanieErrorBox(_error),
          OvaniePrimaryButton(label: 'Continuer', onPressed: _continue),
        ],
      ),
    );
  }
}

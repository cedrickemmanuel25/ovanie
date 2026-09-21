import 'package:flutter/material.dart';

import '../../app/theme.dart';
import '../../shared/widgets/ovanie_widgets.dart';
import '../driver/data/driver_repository.dart';
import 'availability_screen.dart';
import 'onboarding_data.dart';

class ZonesScreen extends StatefulWidget {
  const ZonesScreen({super.key, required this.data});

  final OnboardingData data;

  @override
  State<ZonesScreen> createState() => _ZonesScreenState();
}

class _ZonesScreenState extends State<ZonesScreen> {
  String? _error;
  late Future<List<TerritoryCommune>> _communesFuture;

  @override
  void initState() {
    super.initState();
    _communesFuture = DriverRepository.instance.fetchAvailableCommunes();
  }

  void _retry() {
    setState(() {
      _communesFuture = DriverRepository.instance.fetchAvailableCommunes();
    });
  }

  void _toggle(TerritoryCommune commune) {
    setState(() {
      widget.data.toggleZone(commune);
      _error = null;
    });
  }

  void _continue() {
    if (widget.data.zones.isEmpty) {
      setState(
        () => _error = 'Sélectionnez au moins une commune d’intervention.',
      );
      return;
    }
    Navigator.of(context).push(
      MaterialPageRoute(builder: (_) => AvailabilityScreen(data: widget.data)),
    );
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
            step: 2,
            total: 5,
            title: 'Vos zones d’intervention',
          ),
          const SizedBox(height: 8),
          const Text(
            'Sélectionnez les communes d’Abidjan où vous pouvez effectuer des livraisons.',
            style: TextStyle(
              color: OvanieColors.muted,
              fontSize: 13.5,
              height: 1.4,
            ),
          ),
          const SizedBox(height: 20),
          FutureBuilder<List<TerritoryCommune>>(
            future: _communesFuture,
            builder: (context, snapshot) {
              if (snapshot.connectionState != ConnectionState.done) {
                return const Padding(
                  padding: EdgeInsets.symmetric(vertical: 24),
                  child: Center(child: CircularProgressIndicator()),
                );
              }
              if (snapshot.hasError) {
                return Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    const OvanieErrorBox(
                      'Impossible de charger la liste des communes. Vérifiez votre connexion et réessayez.',
                    ),
                    const SizedBox(height: 12),
                    OutlinedButton(
                      onPressed: _retry,
                      child: const Text('Réessayer'),
                    ),
                  ],
                );
              }

              final communes = snapshot.data ?? const <TerritoryCommune>[];
              // Une commune retirée par la Logistique entre-temps ne doit plus
              // rester sélectionnée dans le formulaire en cours.
              widget.data.zones.removeWhere(
                (id, name) => !communes.any((commune) => commune.id == id),
              );

              if (communes.isEmpty) {
                return const OvanieErrorBox(
                  'Aucune commune n’est actuellement ouverte à l’inscription. Contactez la Logistique OVANIE.',
                );
              }

              final selectedCount = widget.data.zones.length;
              return Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Align(
                    alignment: Alignment.centerRight,
                    child: Container(
                      padding: const EdgeInsets.symmetric(
                        horizontal: 12,
                        vertical: 7,
                      ),
                      decoration: BoxDecoration(
                        color: selectedCount == 0
                            ? OvanieColors.border.withValues(alpha: .5)
                            : OvanieColors.greenLight,
                        borderRadius: BorderRadius.circular(999),
                      ),
                      child: Text(
                        selectedCount == 0
                            ? 'Aucune commune sélectionnée'
                            : '$selectedCount commune${selectedCount > 1 ? 's' : ''} sélectionnée${selectedCount > 1 ? 's' : ''}',
                        style: TextStyle(
                          color: selectedCount == 0
                              ? OvanieColors.muted
                              : OvanieColors.greenDark,
                          fontWeight: FontWeight.w800,
                          fontSize: 12,
                        ),
                      ),
                    ),
                  ),
                  const SizedBox(height: 14),
                  LayoutBuilder(
                    builder: (context, constraints) {
                      final columns =
                          constraints.maxWidth < 290 ||
                              MediaQuery.textScalerOf(context).scale(14) > 18
                          ? 2
                          : 3;
                      final width =
                          (constraints.maxWidth - (columns - 1) * 8) / columns;
                      return Wrap(
                        spacing: 8,
                        runSpacing: 10,
                        children: [
                          for (final commune in communes)
                            SizedBox(
                              width: width,
                              child: OvanieChoiceChip(
                                label: commune.name,
                                selected: widget.data.hasZone(commune),
                                onTap: () => _toggle(commune),
                              ),
                            ),
                        ],
                      );
                    },
                  ),
                ],
              );
            },
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

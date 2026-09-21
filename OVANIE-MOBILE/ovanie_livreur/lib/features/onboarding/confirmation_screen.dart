import 'package:flutter/material.dart';

import '../../app/theme.dart';
import '../../core/network/api_client.dart';
import '../../shared/widgets/ovanie_widgets.dart';
import '../driver/data/driver_repository.dart';
import '../home/home_screen.dart';
import 'onboarding_data.dart';

class ConfirmationScreen extends StatefulWidget {
  const ConfirmationScreen({super.key, required this.data});

  final OnboardingData data;

  @override
  State<ConfirmationScreen> createState() => _ConfirmationScreenState();
}

class _ConfirmationScreenState extends State<ConfirmationScreen> {
  bool _submitting = false;
  String? _error;
  bool _submitted = false;

  Future<void> _submit() async {
    setState(() {
      _submitting = true;
      _error = null;
    });
    final data = widget.data;
    try {
      await DriverRepository.instance.submitOnboarding(
        profilePhoto: data.profilePhoto,
        zoneIds: data.zones.keys.toList(),
        availabilities: data.availabilities.toList(),
        vehicleType: data.vehicleType ?? '',
        plateNumber: data.plateNumber,
        vehicleFiles: VehicleFiles(
          vehiclePhoto: data.vehiclePhoto,
          platePhoto: data.platePhoto,
          vehicleRegistrationDocument: data.vehicleRegistrationDocument,
          supportingDocuments: data.supportingDocuments,
        ),
      );
      if (mounted) setState(() => _submitted = true);
    } catch (error) {
      if (mounted) setState(() => _error = ApiClient.friendlyError(error));
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  Widget _summaryTile(String label, String value, {IconData icon = Icons.circle_outlined}) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 14),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 30,
            height: 30,
            margin: const EdgeInsets.only(top: 1),
            decoration: BoxDecoration(color: OvanieColors.greenLight, borderRadius: BorderRadius.circular(9)),
            child: Icon(icon, size: 15, color: OvanieColors.greenDark),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(label, style: const TextStyle(color: OvanieColors.muted, fontSize: 12.5, fontWeight: FontWeight.w600)),
                const SizedBox(height: 2),
                Text(
                  value.isEmpty ? '—' : value,
                  style: const TextStyle(color: OvanieColors.text, fontWeight: FontWeight.w700, fontSize: 14.5),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _sectionCard({required String title, required IconData badgeIcon, required List<Widget> rows}) {
    return Container(
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(20),
        boxShadow: [
          BoxShadow(color: Colors.black.withValues(alpha: .04), blurRadius: 16, offset: const Offset(0, 6)),
        ],
      ),
      padding: const EdgeInsets.all(18),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                width: 32,
                height: 32,
                decoration: const BoxDecoration(color: OvanieColors.green, shape: BoxShape.circle),
                child: Icon(badgeIcon, size: 16, color: Colors.white),
              ),
              const SizedBox(width: 10),
              Text(title, style: const TextStyle(fontWeight: FontWeight.w900, color: OvanieColors.text, fontSize: 15.5)),
            ],
          ),
          const Divider(height: 24, color: OvanieColors.border),
          ...rows,
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final data = widget.data;

    if (_submitted) {
      return Scaffold(
        backgroundColor: OvanieColors.green,
        body: SafeArea(
          child: Padding(
            padding: const EdgeInsets.all(28),
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                TweenAnimationBuilder<double>(
                  tween: Tween(begin: 0, end: 1),
                  duration: const Duration(milliseconds: 550),
                  curve: Curves.elasticOut,
                  builder: (context, value, child) => Transform.scale(scale: value, child: child),
                  child: Container(
                    width: 108,
                    height: 108,
                    decoration: BoxDecoration(
                      color: Colors.white.withValues(alpha: .16),
                      shape: BoxShape.circle,
                    ),
                    child: Center(
                      child: Container(
                        width: 78,
                        height: 78,
                        decoration: const BoxDecoration(color: Colors.white, shape: BoxShape.circle),
                        child: const Icon(Icons.check_rounded, color: OvanieColors.green, size: 46),
                      ),
                    ),
                  ),
                ),
                const SizedBox(height: 32),
                const Text(
                  'Dossier envoyé !',
                  textAlign: TextAlign.center,
                  style: TextStyle(color: Colors.white, fontSize: 28, fontWeight: FontWeight.w900, letterSpacing: -.3),
                ),
                const SizedBox(height: 12),
                Text(
                  'Votre dossier a bien été transmis à OVANIE Logistics. '
                  'Vous devez maintenant attendre sa validation.',
                  textAlign: TextAlign.center,
                  style: TextStyle(color: Colors.white.withValues(alpha: .9), fontSize: 15, height: 1.5, fontWeight: FontWeight.w500),
                ),
                const SizedBox(height: 36),
                SizedBox(
                  width: double.infinity,
                  child: ElevatedButton(
                    onPressed: () => Navigator.of(context).pushAndRemoveUntil(
                      MaterialPageRoute(builder: (_) => const HomeScreen()),
                      (_) => false,
                    ),
                    style: ElevatedButton.styleFrom(
                      backgroundColor: Colors.white,
                      foregroundColor: OvanieColors.greenDark,
                      minimumSize: const Size.fromHeight(56),
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(28)),
                      elevation: 0,
                    ),
                    child: const Text(
                      'Retour à l’accueil',
                      style: TextStyle(fontSize: 16, fontWeight: FontWeight.w800),
                    ),
                  ),
                ),
              ],
            ),
          ),
        ),
      );
    }

    return Scaffold(
      appBar: AppBar(
        elevation: 0,
        backgroundColor: OvanieColors.background,
        foregroundColor: OvanieColors.text,
        title: const Text('Récapitulatif'),
      ),
      backgroundColor: OvanieColors.background,
      body: SafeArea(
        child: OvanieWizardBody(
          content: [
            const OvanieStepHeader(step: 5, total: 5, title: 'Vérifiez votre dossier'),
              const SizedBox(height: 8),
              const Text(
                'Vérifiez vos informations avant l’envoi. Vous pouvez revenir en arrière pour corriger une étape.',
                style: TextStyle(color: OvanieColors.muted, fontSize: 13.5, height: 1.4),
              ),
              const SizedBox(height: 20),
              _sectionCard(
                title: 'Identité',
                badgeIcon: Icons.person_rounded,
                rows: [
                  _summaryTile('Prénom', data.firstName, icon: Icons.badge_outlined),
                  _summaryTile('Nom', data.lastName, icon: Icons.badge_outlined),
                  _summaryTile('Téléphone', data.phone, icon: Icons.phone_iphone_rounded),
                  _summaryTile('Photo', data.profilePhoto == null ? 'Non ajoutée' : 'Ajoutée', icon: Icons.photo_camera_outlined),
                ],
              ),
              const SizedBox(height: 14),
              _sectionCard(
                title: 'Zones et disponibilités',
                badgeIcon: Icons.map_rounded,
                rows: [
                  _summaryTile('Communes', data.zones.values.join(', '), icon: Icons.location_on_outlined),
                  _summaryTile('Jours', data.availabilities.join(', '), icon: Icons.calendar_today_rounded),
                ],
              ),
              const SizedBox(height: 14),
              _sectionCard(
                title: 'Véhicule',
                badgeIcon: Icons.directions_car_filled_rounded,
                rows: [
                  _summaryTile('Type', data.vehicleType ?? '', icon: Icons.two_wheeler_rounded),
                  _summaryTile('Immatriculation', data.plateNumber, icon: Icons.confirmation_number_outlined),
                  _summaryTile('Couleur', 'Détectée automatiquement depuis la photo', icon: Icons.auto_awesome_outlined),
                  _summaryTile('Photo véhicule', data.vehiclePhoto == null ? 'Non ajoutée' : 'Ajoutée', icon: Icons.photo_camera_outlined),
                  _summaryTile('Photo plaque', data.platePhoto == null ? 'Non ajoutée' : 'Ajoutée', icon: Icons.photo_camera_outlined),
                  _summaryTile(
                    'Carte grise',
                    data.vehicleRegistrationDocument == null ? 'Non ajoutée' : 'Ajoutée',
                    icon: Icons.description_outlined,
                  ),
                  _summaryTile(
                    'Justificatifs',
                    data.supportingDocuments.isEmpty
                        ? 'Aucun'
                        : '${data.supportingDocuments.length} document(s)',
                    icon: Icons.attach_file_rounded,
                  ),
                ],
              ),
              const SizedBox(height: 8),
              Center(
                child: TextButton.icon(
                  onPressed: () => Navigator.of(context).pop(),
                  icon: const Icon(Icons.edit_outlined, size: 18),
                  label: const Text('Corriger une étape précédente'),
                ),
              ),
          ],
          footer: [
            if (_error != null) OvanieErrorBox(_error),
            OvaniePrimaryButton(
              label: _submitting ? 'Envoi en cours…' : 'Confirmer et envoyer mon dossier',
              onPressed: _submit,
              loading: _submitting,
            ),
          ],
        ),
      ),
    );
  }
}

import 'package:flutter/material.dart';

import '../../app/theme.dart';
import '../../core/network/api_client.dart';
import '../../shared/widgets/ovanie_widgets.dart';
import '../driver/data/driver_repository.dart';
import 'onboarding_data.dart';
import 'personal_info_screen.dart';

class PhoneCheckScreen extends StatefulWidget {
  const PhoneCheckScreen({super.key});

  @override
  State<PhoneCheckScreen> createState() => _PhoneCheckScreenState();
}

class _PhoneCheckScreenState extends State<PhoneCheckScreen> {
  final _form = GlobalKey<FormState>();
  final _phone = TextEditingController();
  bool _submitting = false;
  String? _error;
  String? _blockingMessage;
  String? _loginHintMessage;

  @override
  void dispose() {
    _phone.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    FocusScope.of(context).unfocus();
    if (!_form.currentState!.validate()) return;
    setState(() {
      _submitting = true;
      _error = null;
      _blockingMessage = null;
      _loginHintMessage = null;
    });
    try {
      final phone = normalizeDriverPhone(_phone.text);
      final result = await DriverRepository.instance.checkPhone(phone);

      if (!result.registered) {
        setState(() {
          _blockingMessage =
              'Ce numéro n’a pas encore été enregistré par OVANIE Logistics. '
              'Veuillez contacter l’équipe Logistique pour devenir livreur partenaire OVANIE.';
        });
        return;
      }

      if (result.canLogin) {
        setState(() {
          _loginHintMessage =
              'Ce numéro possède déjà un compte livreur. Utilisez plutôt l’écran de connexion.';
        });
        return;
      }

      final data = OnboardingData()..phone = phone;
      if (result.driver != null) {
        data.applyDriver(result.driver!);
      }
      data.onboardingStatus ??= result.onboardingStatus;

      if (!mounted) return;
      Navigator.of(
        context,
      ).push(MaterialPageRoute(builder: (_) => PersonalInfoScreen(data: data)));
    } catch (error) {
      if (mounted) setState(() => _error = ApiClient.friendlyError(error));
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return OvanieHeroScaffold(
      keepHeroWithKeyboard: true,
      heroHeightFactor: 0.25,
      showBack: true,
      appBarTitle: 'Créer mon compte livreur',
      showWordmark: true,
      showTagline: true,
      child: OvanieFormBody(
        children: [
          const OvanieSectionHeading(
            title: 'Vérification de votre numéro',
            subtitle:
                'Entrez le numéro de téléphone communiqué à l’équipe Logistique OVANIE '
                'lors de votre pré-enregistrement.',
          ),
          const SizedBox(height: 22),
          Form(
            key: _form,
            child: OvaniePhoneField(
              controller: _phone,
              onFieldSubmitted: (_) => _submit(),
              validator: (value) => (value ?? '').trim().isEmpty
                  ? 'Renseignez votre numéro de téléphone.'
                  : null,
            ),
          ),
          const SizedBox(height: 20),
          if (_error != null) OvanieErrorBox(_error),
          if (_blockingMessage != null) ...[
            OvanieInfoBox(
              icon: Icons.block_rounded,
              color: OvanieColors.danger,
              background: OvanieColors.danger.withValues(alpha: .06),
              child: Text(
                _blockingMessage!,
                style: const TextStyle(
                  color: OvanieColors.danger,
                  height: 1.4,
                  fontSize: 13.5,
                ),
              ),
            ),
            const SizedBox(height: 16),
          ],
          if (_loginHintMessage != null) ...[
            OvanieInfoBox(
              icon: Icons.login_rounded,
              child: Text(
                _loginHintMessage!,
                style: const TextStyle(
                  color: OvanieColors.text,
                  height: 1.4,
                  fontSize: 13.5,
                ),
              ),
            ),
            const SizedBox(height: 16),
          ],
          OvaniePrimaryButton(
            label: _submitting ? 'Vérification…' : 'Continuer',
            onPressed: _submit,
            loading: _submitting,
          ),
        ],
      ),
    );
  }
}

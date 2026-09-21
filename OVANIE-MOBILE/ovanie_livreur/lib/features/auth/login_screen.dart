import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';

import '../../app/theme.dart';
import '../../core/network/api_client.dart';
import '../../shared/widgets/ovanie_widgets.dart';
import '../driver/data/driver_repository.dart';
import '../onboarding/phone_check_screen.dart';
import 'otp_screen.dart';

class LoginScreen extends StatefulWidget {
  const LoginScreen({super.key});

  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  final _form = GlobalKey<FormState>();
  final _phone = TextEditingController();
  bool _submitting = false;
  String? _error;

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
    });
    try {
      final phone = normalizeDriverPhone(_phone.text);
      final result = await DriverRepository.instance.login(phone);
      if (!mounted) return;
      Navigator.of(context).push(
        MaterialPageRoute(
          builder: (_) => OtpScreen(phone: phone, expiresIn: result.expiresIn),
        ),
      );
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
      heroHeightFactor: 0.30,
      appBarTitle: 'Bienvenue chez OVANIE',
      showWordmark: true,
      showTagline: true,
      child: OvanieFormBody(
        children: [
          Text(
            'Heureux de vous retrouver',
            style: GoogleFonts.plusJakartaSans(
              color: OvanieColors.text,
              fontSize: 24,
              fontWeight: FontWeight.w800,
              letterSpacing: -.3,
            ),
          ),
          const SizedBox(height: 6),
          const Text(
            'Connectez-vous pour accéder à votre espace livreur.',
            style: TextStyle(
              color: OvanieColors.muted,
              fontSize: 14,
              height: 1.4,
            ),
          ),
          const SizedBox(height: 22),
          const Text(
            'Numéro de téléphone',
            style: TextStyle(
              color: OvanieColors.text,
              fontSize: 15,
              fontWeight: FontWeight.w700,
            ),
          ),
          const SizedBox(height: 10),
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
          const SizedBox(height: 22),
          if (_error != null) OvanieErrorBox(_error),
          OvaniePrimaryButton(
            label: _submitting ? 'Connexion en cours…' : 'Se connecter',
            onPressed: _submit,
            loading: _submitting,
          ),
          const SizedBox(height: 20),
          Center(
            child: TextButton(
              onPressed: () => Navigator.of(context).push(
                MaterialPageRoute(builder: (_) => const PhoneCheckScreen()),
              ),
              child: const Text(
                'Première inscription ? Créer mon compte livreur',
                textAlign: TextAlign.center,
                style: TextStyle(
                  color: OvanieColors.greenDark,
                  fontWeight: FontWeight.w800,
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

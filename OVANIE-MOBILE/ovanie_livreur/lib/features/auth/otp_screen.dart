import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../../app/theme.dart';
import '../../core/network/api_client.dart';
import '../../shared/widgets/ovanie_widgets.dart';
import '../driver/data/driver_repository.dart';
import '../home/home_screen.dart';
import '../onboarding/onboarding_data.dart';
import '../onboarding/personal_info_screen.dart';

/// Écran de vérification du code OTP envoyé par SMS lors de la connexion.
///
/// Contrat backend : voir DriverRepository.verifyOtp / resendOtp
/// (POST /driver/auth/login/verify et /driver/auth/login/resend).
class OtpScreen extends StatefulWidget {
  const OtpScreen({super.key, required this.phone, required this.expiresIn});

  final String phone;

  /// Durée de validité du code en secours, en secondes (reçue de /login).
  final int expiresIn;

  @override
  State<OtpScreen> createState() => _OtpScreenState();
}

class _OtpScreenState extends State<OtpScreen> {
  final _code = TextEditingController();
  final _focusNode = FocusNode();
  bool _submitting = false;
  bool _resending = false;
  String? _error;
  Timer? _timer;
  late int _secondsLeft;

  @override
  void initState() {
    super.initState();
    _secondsLeft = widget.expiresIn;
    _startTimer();
    _code.addListener(() => setState(() {}));
  }

  void _startTimer() {
    _timer?.cancel();
    _timer = Timer.periodic(const Duration(seconds: 1), (timer) {
      if (!mounted) return;
      if (_secondsLeft <= 0) {
        timer.cancel();
        return;
      }
      setState(() => _secondsLeft -= 1);
    });
  }

  @override
  void dispose() {
    _timer?.cancel();
    _code.dispose();
    _focusNode.dispose();
    super.dispose();
  }

  String get _countdownLabel {
    final minutes = (_secondsLeft ~/ 60).toString().padLeft(2, '0');
    final seconds = (_secondsLeft % 60).toString().padLeft(2, '0');
    return '$minutes:$seconds';
  }

  Future<void> _submit() async {
    final code = _code.text.trim();
    if (code.length != 6) {
      setState(() => _error = 'Entrez les 6 chiffres du code reçu par SMS.');
      return;
    }
    FocusScope.of(context).unfocus();
    setState(() {
      _submitting = true;
      _error = null;
    });
    try {
      final result = await DriverRepository.instance.verifyOtp(
        phone: widget.phone,
        otp: code,
      );
      if (!mounted) return;

      // La vérification OTP confirme uniquement le numéro de téléphone : elle
      // ne signifie pas que l'inscription est terminée. Tant que le dossier
      // n'est ni "active" ni "pending_review" (déjà soumis, en cours de
      // vérification par la Logistique), le livreur doit reprendre le tunnel
      // d'inscription — jamais être envoyé directement à l'accueil.
      final status = result.driver?.onboardingStatus;
      if (status == 'invited' || status == 'rejected') {
        final data = OnboardingData()..phone = widget.phone;
        if (result.driver != null) data.applyDriver(result.driver!);
        Navigator.of(context).pushAndRemoveUntil(
          MaterialPageRoute(builder: (_) => PersonalInfoScreen(data: data)),
          (_) => false,
        );
        return;
      }

      Navigator.of(context).pushAndRemoveUntil(
        MaterialPageRoute(builder: (_) => const HomeScreen()),
        (_) => false,
      );
    } catch (error) {
      if (mounted) setState(() => _error = ApiClient.friendlyError(error));
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  Future<void> _resend() async {
    if (_secondsLeft > 0 || _resending) return;
    setState(() {
      _resending = true;
      _error = null;
    });
    try {
      final result = await DriverRepository.instance.resendOtp(widget.phone);
      if (!mounted) return;
      setState(() => _secondsLeft = result.expiresIn);
      _startTimer();
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Un nouveau code vous a été envoyé par SMS.'),
        ),
      );
    } catch (error) {
      if (mounted) setState(() => _error = ApiClient.friendlyError(error));
    } finally {
      if (mounted) setState(() => _resending = false);
    }
  }

  Widget _otpBox(int index) {
    final text = _code.text;
    final filled = index < text.length;
    final isCursor = index == text.length;
    return Container(
      width: 46,
      height: 54,
      alignment: Alignment.center,
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(
          color: isCursor ? OvanieColors.green : OvanieColors.border,
          width: isCursor ? 1.6 : 1,
        ),
      ),
      child: Text(
        filled ? text[index] : '',
        style: const TextStyle(
          color: OvanieColors.text,
          fontSize: 22,
          fontWeight: FontWeight.w800,
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final canResend = _secondsLeft <= 0 && !_resending;
    final expired = _secondsLeft <= 0;
    return OvanieHeroScaffold(
      keepHeroWithKeyboard: true,
      heroHeightFactor: 0.22,
      showBack: true,
      appBarTitle: 'Vérification',
      child: OvanieFormBody(
        children: [
          const OvanieSectionHeading(
            title: 'Vérification OTP',
            subtitle: 'Entrez le code à 6 chiffres envoyé par SMS.',
          ),
          const SizedBox(height: 18),
          Container(
            width: double.infinity,
            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
            decoration: BoxDecoration(
              color: OvanieColors.greenLight,
              borderRadius: BorderRadius.circular(14),
            ),
            child: Row(
              children: [
                const OvanieFlagBadge(),
                const SizedBox(width: 8),
                const Text(
                  '+225',
                  style: TextStyle(
                    color: OvanieColors.text,
                    fontWeight: FontWeight.w700,
                    fontSize: 14,
                  ),
                ),
                const SizedBox(width: 8),
                Expanded(
                  child: Text(
                    formatDriverPhone(widget.phone),
                    style: const TextStyle(
                      color: OvanieColors.text,
                      fontWeight: FontWeight.w800,
                      fontSize: 14,
                    ),
                  ),
                ),
                GestureDetector(
                  onTap: () => Navigator.of(context).maybePop(),
                  child: const Text(
                    'Modifier',
                    style: TextStyle(
                      color: OvanieColors.greenDark,
                      fontWeight: FontWeight.w800,
                      fontSize: 13,
                      decoration: TextDecoration.underline,
                    ),
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 24),
          const Text(
            'Code de vérification',
            style: TextStyle(
              color: OvanieColors.text,
              fontSize: 14.5,
              fontWeight: FontWeight.w700,
            ),
          ),
          const SizedBox(height: 12),
          GestureDetector(
            onTap: () => _focusNode.requestFocus(),
            child: Stack(
              children: [
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    for (var i = 0; i < 6; i++)
                      Expanded(
                        child: Padding(
                          padding: EdgeInsets.only(right: i < 5 ? 6 : 0),
                          child: _otpBox(i),
                        ),
                      ),
                  ],
                ),
                SizedBox(
                  width: 0,
                  height: 0,
                  child: TextField(
                    controller: _code,
                    focusNode: _focusNode,
                    autofillHints: const [AutofillHints.oneTimeCode],
                    keyboardType: TextInputType.number,
                    maxLength: 6,
                    inputFormatters: [FilteringTextInputFormatter.digitsOnly],
                    decoration: const InputDecoration(
                      counterText: '',
                      border: InputBorder.none,
                    ),
                    onSubmitted: (_) => _submit(),
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 18),
          Center(
            child: Container(
              padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
              decoration: BoxDecoration(
                color: expired
                    ? OvanieColors.danger.withValues(alpha: .06)
                    : OvanieColors.greenLight,
                borderRadius: BorderRadius.circular(999),
              ),
              child: Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Icon(
                    expired
                        ? Icons.error_outline_rounded
                        : Icons.timer_outlined,
                    size: 15,
                    color: expired
                        ? OvanieColors.danger
                        : OvanieColors.greenDark,
                  ),
                  const SizedBox(width: 6),
                  Flexible(
                    child: Text(
                      expired
                          ? 'Le code a expiré'
                          : 'Expire dans $_countdownLabel',
                      style: TextStyle(
                        color: expired
                            ? OvanieColors.danger
                            : OvanieColors.greenDark,
                        fontWeight: FontWeight.w700,
                        fontSize: 12.5,
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ),
          const SizedBox(height: 20),
          Column(
            children: [
              const Text(
                'Vous n’avez pas reçu le code ?',
                textAlign: TextAlign.center,
                style: TextStyle(color: OvanieColors.muted, fontSize: 13.5),
              ),
              const SizedBox(height: 4),
              GestureDetector(
                onTap: canResend ? _resend : null,
                child: Text(
                  _resending ? 'Envoi en cours…' : 'Renvoyer le code',
                  style: TextStyle(
                    color: canResend
                        ? OvanieColors.greenDark
                        : OvanieColors.muted,
                    fontWeight: FontWeight.w800,
                    fontSize: 13.5,
                    decoration: canResend
                        ? TextDecoration.underline
                        : TextDecoration.none,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          if (_error != null) OvanieErrorBox(_error),
          OvaniePrimaryButton(
            label: _submitting ? 'Vérification…' : 'Vérifier',
            onPressed: _submit,
            loading: _submitting,
          ),
        ],
      ),
    );
  }
}

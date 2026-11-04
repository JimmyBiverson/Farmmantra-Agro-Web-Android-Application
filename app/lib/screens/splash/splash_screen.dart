import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../core/theme/app_colors.dart';
import '../../providers/auth_provider.dart';

class SplashScreen extends StatefulWidget {
  const SplashScreen({super.key});
  @override
  State<SplashScreen> createState() => _SplashScreenState();
}

class _SplashScreenState extends State<SplashScreen>
    with SingleTickerProviderStateMixin {
  late final AnimationController _intro = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 650),
  )..forward();
  late final Animation<double> _fade = CurvedAnimation(
    parent: _intro,
    curve: Curves.easeOutCubic,
  );

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (mounted) context.read<AuthProvider>().initialize();
    });
  }

  @override
  void dispose() {
    _intro.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    backgroundColor: AppColors.primaryGreenDark,
    body: Stack(
      fit: StackFit.expand,
      children: [
        Image.asset(
          'assets/images/farmmantra-fields.png',
          fit: BoxFit.cover,
          excludeFromSemantics: true,
        ),
        const DecoratedBox(
          decoration: BoxDecoration(
            gradient: LinearGradient(
              begin: Alignment.topCenter,
              end: Alignment.bottomCenter,
              colors: [Color(0x99123B2D), Color(0xFA123B2D)],
            ),
          ),
        ),
        SafeArea(
          child: Padding(
            padding: const EdgeInsets.all(32),
            child: FadeTransition(
              opacity: _fade,
              child: Column(
                children: [
                  const Spacer(),
                  Container(
                    padding: const EdgeInsets.all(22),
                    decoration: BoxDecoration(
                      color: AppColors.accentGold,
                      borderRadius: BorderRadius.circular(27),
                    ),
                    child: const Icon(
                      Icons.eco_rounded,
                      size: 54,
                      color: AppColors.primaryGreenDark,
                    ),
                  ),
                  const SizedBox(height: 24),
                  const Text(
                    'Farmmantra',
                    style: TextStyle(
                      color: Colors.white,
                      fontSize: 37,
                      fontWeight: FontWeight.w600,
                      letterSpacing: -1.2,
                    ),
                  ),
                  const SizedBox(height: 9),
                  const Text(
                    'AGRO CHEMICALS LIMITED',
                    style: TextStyle(
                      color: Colors.white70,
                      fontSize: 10,
                      letterSpacing: 2.4,
                    ),
                  ),
                  const SizedBox(height: 28),
                  const Text(
                    'Growing together. Every day.',
                    textAlign: TextAlign.center,
                    style: TextStyle(color: AppColors.accentGold, fontSize: 14),
                  ),
                  const Spacer(),
                  const SizedBox(
                    width: 22,
                    height: 22,
                    child: CircularProgressIndicator(
                      strokeWidth: 2,
                      color: AppColors.accentGold,
                    ),
                  ),
                  const SizedBox(height: 16),
                  const Text(
                    'Preparing your workspace',
                    style: TextStyle(color: Colors.white60, fontSize: 11),
                  ),
                  const SizedBox(height: 22),
                ],
              ),
            ),
          ),
        ),
      ],
    ),
  );
}

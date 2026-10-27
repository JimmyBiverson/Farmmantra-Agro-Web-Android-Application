import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../../core/theme/app_colors.dart';
import '../../../providers/auth_provider.dart';
import '../../../services/api/api_service.dart';
import '../../../widgets/common/app_button.dart';

/// Franchise partners must read and accept the terms & conditions on their
/// first login (and whenever Farmmantra publishes a new version).
class TermsScreen extends StatefulWidget {
  /// When true the screen is only for reading (opened from the menu).
  final bool readOnly;

  const TermsScreen({super.key, this.readOnly = false});

  @override
  State<TermsScreen> createState() => _TermsScreenState();
}

class _TermsScreenState extends State<TermsScreen> {
  String? _text;
  String? _error;
  bool _accepted = false;
  bool _isSubmitting = false;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() => _error = null);
    try {
      final data = await context.read<ApiService>().getTerms();
      if (!mounted) return;
      setState(() => _text = data['text']?.toString() ?? '');
    } catch (e) {
      if (!mounted) return;
      setState(() => _error = e.toString().replaceFirst('Exception: ', ''));
    }
  }

  Future<void> _accept() async {
    setState(() => _isSubmitting = true);
    try {
      await context.read<ApiService>().acceptTerms();
      if (!mounted) return;
      context.read<AuthProvider>().markTermsAccepted();
      Navigator.of(context).pushReplacementNamed('/franchise/dashboard');
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(e.toString().replaceFirst('Exception: ', '')),
          backgroundColor: AppColors.error,
        ),
      );
    } finally {
      if (mounted) setState(() => _isSubmitting = false);
    }
  }

  Future<void> _logout() async {
    await context.read<AuthProvider>().logout();
    if (!mounted) return;
    Navigator.of(context).pushNamedAndRemoveUntil('/login', (_) => false);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Terms & Conditions'),
        automaticallyImplyLeading: widget.readOnly,
        actions: [
          if (!widget.readOnly)
            TextButton(onPressed: _logout, child: const Text('Log out')),
        ],
      ),
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              if (!widget.readOnly)
                const Padding(
                  padding: EdgeInsets.only(bottom: 12),
                  child: Text(
                    'Please read and accept the terms and conditions to continue.',
                    style: TextStyle(
                      fontWeight: FontWeight.w600,
                      color: AppColors.textPrimary,
                    ),
                  ),
                ),
              Expanded(
                child: Container(
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(
                    border: Border.all(color: AppColors.border),
                    borderRadius: BorderRadius.circular(12),
                    color: Colors.white,
                  ),
                  child: _error != null
                      ? Center(
                          child: Column(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              Text(_error!, textAlign: TextAlign.center),
                              const SizedBox(height: 8),
                              TextButton(onPressed: _load, child: const Text('Retry')),
                            ],
                          ),
                        )
                      : _text == null
                      ? const Center(child: CircularProgressIndicator())
                      : SingleChildScrollView(
                          child: Text(
                            _text!,
                            style: const TextStyle(height: 1.5, fontSize: 14),
                          ),
                        ),
                ),
              ),
              if (!widget.readOnly) ...[
                const SizedBox(height: 12),
                CheckboxListTile(
                  value: _accepted,
                  onChanged: _text == null
                      ? null
                      : (v) => setState(() => _accepted = v ?? false),
                  controlAffinity: ListTileControlAffinity.leading,
                  contentPadding: EdgeInsets.zero,
                  title: const Text('I have read and I accept these terms and conditions.'),
                ),
                AppButton(
                  label: 'Accept and continue',
                  icon: Icons.check_circle_outline,
                  isLoading: _isSubmitting,
                  onPressed: _accepted && !_isSubmitting ? _accept : null,
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }
}

import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import 'package:provider/provider.dart';
import '../../features/operations/data/operations_api.dart';
import '../../features/operations/presentation/workspace_ui.dart';
import '../../providers/site_settings_provider.dart';
import '../../widgets/common/brand_identity.dart';

class AdminBrandingScreen extends StatefulWidget {
  const AdminBrandingScreen({super.key});
  @override
  State<AdminBrandingScreen> createState() => _AdminBrandingScreenState();
}

class _AdminBrandingScreenState extends State<AdminBrandingScreen> {
  bool busy = false;
  Future<void> upload(String type) async {
    setState(() => busy = true);
    final api = operationsApi(context);
    try {
      final file = await ImagePicker().pickImage(source: ImageSource.gallery);
      if (file == null || !mounted) return;
      await api.upload(
        'admin/settings/upload-image',
        {'type': type},
        files: {
          'image': [OperationsFile(file.name, await file.readAsBytes())],
        },
      );
      if (!mounted) return;
      await context.read<SiteSettingsProvider>().reload();
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text(
              'Branding saved. Launcher changes require a new APK.',
            ),
          ),
        );
      }
    } catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(operationError(error))));
      }
    } finally {
      api.dispose();
      if (mounted) setState(() => busy = false);
    }
  }

  @override
  Widget build(BuildContext context) => WorkspacePage(
    title: 'Brand identity',
    child: ListView(
      padding: const EdgeInsets.all(24),
      children: [
        const BrandIdentity(size: 64),
        const SizedBox(height: 28),
        const Text(
          'Make it yours',
          style: TextStyle(fontSize: 28, fontWeight: FontWeight.w700),
        ),
        const SizedBox(height: 12),
        const Text(
          'Your website and app use the same saved brand. Upload PNG, JPEG or WebP images up to 2 MB. Use a square PNG of at least 512 × 512 pixels for the app icon.',
        ),
        const SizedBox(height: 24),
        for (final entry in {
          'logo': 'Change site logo',
          'favicon': 'Change browser favicon',
          'app_icon': 'Change app icon',
        }.entries)
          Padding(
            padding: const EdgeInsets.only(bottom: 12),
            child: FilledButton.tonalIcon(
              onPressed: busy ? null : () => upload(entry.key),
              icon: const Icon(Icons.upload_outlined),
              label: Text(entry.value),
            ),
          ),
        if (busy) const LinearProgressIndicator(),
        const SizedBox(height: 24),
        const Center(child: BrandIdentity(size: 96, iconOnly: true)),
        const SizedBox(height: 20),
        const Text(
          'Website changes appear on the next page load. The app refreshes branding when reopened and once a minute while active. The Android home-screen icon is part of the installed APK: upload its artwork here, then rebuild and install the next app version.',
        ),
      ],
    ),
  );
}

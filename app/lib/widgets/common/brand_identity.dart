import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../providers/site_settings_provider.dart';

/// Server-managed branding, with a bundled offline fallback.
class BrandIdentity extends StatelessWidget {
  const BrandIdentity({
    super.key,
    this.size = 52,
    this.light = false,
    this.iconOnly = false,
    this.useAppIcon = false,
  });
  final double size;
  final bool light;
  final bool iconOnly;
  final bool useAppIcon;

  @override
  Widget build(BuildContext context) {
    final settings = context.watch<SiteSettingsProvider?>()?.settings;
    final url = iconOnly || useAppIcon
        ? settings?.appIconUrl
        : settings?.logoUrl;
    Widget fallback() =>
        Image.asset('assets/images/farmmantra-icon.png', fit: BoxFit.contain);
    final image = ClipRRect(
      borderRadius: BorderRadius.circular(size * .23),
      child: SizedBox(
        width: size,
        height: size,
        child: url == null || url.isEmpty
            ? fallback()
            : Image.network(
                url,
                fit: BoxFit.contain,
                errorBuilder: (_, error, stack) => fallback(),
                loadingBuilder: (_, child, progress) =>
                    progress == null ? child : fallback(),
              ),
      ),
    );
    if (iconOnly) return image;
    return Row(
      children: [
        image,
        const SizedBox(width: 12),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                settings?.siteName ?? 'Farmmantra',
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
                style: TextStyle(
                  color: light ? Colors.white : const Color(0xFF123D30),
                  fontSize: 20,
                  fontWeight: FontWeight.w700,
                ),
              ),
              Text(
                settings?.siteTagline ?? 'Growing together. Every day.',
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
                style: TextStyle(
                  color: light ? Colors.white70 : Colors.black54,
                  fontSize: 11,
                ),
              ),
            ],
          ),
        ),
      ],
    );
  }
}

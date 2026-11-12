import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:provider/provider.dart';
import 'package:farmmantra/providers/site_settings_provider.dart';
import 'package:farmmantra/services/api/mock_api_service.dart';
import 'package:farmmantra/widgets/common/brand_identity.dart';

class BrandingApi extends MockApiService {
  Map<String, dynamic> data = {'site_name': 'First brand'};
  bool fail = false;
  @override
  Future<Map<String, dynamic>> getSiteSettings() async {
    if (fail) throw Exception('Offline');
    return data;
  }
}

void main() {
  test(
    'reload applies admin changes and retains last brand while offline',
    () async {
      final api = BrandingApi();
      final provider = SiteSettingsProvider(apiService: api);
      await provider.loadSettings();
      expect(provider.siteName, 'First brand');
      api.data = {
        'site_name': 'Updated brand',
        'app_icon_url': 'https://example.com/icon.png',
      };
      await provider.reload();
      expect(provider.siteName, 'Updated brand');
      expect(provider.settings.appIconUrl, 'https://example.com/icon.png');
      api.fail = true;
      await provider.reload();
      expect(provider.siteName, 'Updated brand');
      expect(provider.isLoading, false);
      provider.dispose();
    },
  );

  testWidgets('brand responds to settings changes on a narrow screen', (
    tester,
  ) async {
    final api = BrandingApi();
    final provider = SiteSettingsProvider(apiService: api);
    await provider.loadSettings();
    await tester.pumpWidget(
      ChangeNotifierProvider.value(
        value: provider,
        child: const MaterialApp(
          home: Scaffold(body: SizedBox(width: 240, child: BrandIdentity())),
        ),
      ),
    );
    expect(find.text('First brand'), findsOneWidget);
    api.data = {
      'site_name': 'A very long updated agricultural company name',
      'site_tagline': 'Supporting every farmer',
    };
    await provider.reload();
    await tester.pump();
    expect(find.text('Supporting every farmer'), findsOneWidget);
    expect(tester.takeException(), isNull);
    await tester.pumpWidget(const SizedBox());
    provider.dispose();
  });
}

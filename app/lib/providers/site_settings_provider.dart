import 'package:flutter/material.dart';
import '../models/site_settings.dart';
import '../services/api/api_service.dart';

class SiteSettingsProvider extends ChangeNotifier {
  final ApiService _apiService;

  SiteSettings _settings = SiteSettings.defaults;
  bool _isLoading = false;
  bool _loaded = false;

  SiteSettingsProvider({required ApiService apiService})
    : _apiService = apiService;

  SiteSettings get settings => _settings;
  bool get isLoading => _isLoading;
  bool get loaded => _loaded;

  String get siteName => _settings.siteName;
  String get siteTagline => _settings.siteTagline;
  String? get logoUrl => _settings.logoUrl;
  String get currencySymbol => _settings.currencySymbol;
  String get primaryColor => _settings.primaryColor;

  Future<void> loadSettings() async {
    if (_loaded) return;
    _isLoading = true;
    notifyListeners();

    try {
      final data = await _apiService.getSiteSettings();
      _settings = SiteSettings.fromJson(data);
      _loaded = true;
    } catch (e) {
      // Fall back to defaults – non-critical
    } finally {
      _isLoading = false;
      notifyListeners();
    }
  }

  Future<void> reload() async {
    _loaded = false;
    await loadSettings();
  }
}

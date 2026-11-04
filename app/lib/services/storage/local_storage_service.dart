import 'dart:convert';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

class LocalStorageService {
  static const _keyToken = 'auth_token';
  static const _keyRefreshToken = 'auth_refresh_token';
  static const _keyUser = 'user_data';
  static const _keyTheme = 'theme_mode';
  static const _keyLanguage = 'language';
  static const _keyFirstLaunch = 'first_launch';

  late SharedPreferences _prefs;
  final FlutterSecureStorage _secure = const FlutterSecureStorage();
  String? _token;
  String? _refreshToken;

  Future<void> initialize() async {
    _prefs = await SharedPreferences.getInstance();
    _token = await _secure.read(key: _keyToken);
    _refreshToken = await _secure.read(key: _keyRefreshToken);
    await _prefs.remove(_keyToken);
    await _prefs.remove(_keyRefreshToken);
  }

  // Token management
  Future<void> saveToken(String token) async {
    await _secure.write(key: _keyToken, value: token);
    _token = token;
  }

  String? getToken() {
    return _token;
  }

  Future<void> saveRefreshToken(String token) async {
    await _secure.write(key: _keyRefreshToken, value: token);
    _refreshToken = token;
  }

  String? getRefreshToken() {
    return _refreshToken;
  }

  // User data
  Future<void> saveUser(Map<String, dynamic> userData) async {
    final encoded = jsonEncode(userData);
    await _prefs.setString(_keyUser, encoded);
  }

  Map<String, dynamic>? getUser() {
    final raw = _prefs.getString(_keyUser);
    if (raw == null) return null;
    try {
      return jsonDecode(raw) as Map<String, dynamic>;
    } catch (_) {
      return null;
    }
  }

  // Theme
  Future<void> saveThemeMode(String mode) async {
    await _prefs.setString(_keyTheme, mode);
  }

  String? getThemeMode() {
    return _prefs.getString(_keyTheme);
  }

  // Language
  Future<void> saveLanguage(String lang) async {
    await _prefs.setString(_keyLanguage, lang);
  }

  String? getLanguage() {
    return _prefs.getString(_keyLanguage);
  }

  // First launch
  bool get isFirstLaunch {
    return _prefs.getBool(_keyFirstLaunch) ?? true;
  }

  Future<void> setFirstLaunchDone() async {
    await _prefs.setBool(_keyFirstLaunch, false);
  }

  // Clear all data
  Future<void> clearAll() async {
    await clearAuthData();
    await _prefs.clear();
  }

  Future<void> clearAuthData() async {
    await _secure.delete(key: _keyToken);
    await _secure.delete(key: _keyRefreshToken);
    _token = null;
    _refreshToken = null;
    await _prefs.remove(_keyToken);
    await _prefs.remove(_keyRefreshToken);
    await _prefs.remove(_keyUser);
  }
}

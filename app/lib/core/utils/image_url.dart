import '../constants/api_endpoints.dart';

/// Resolves a server-relative image path (e.g. `products/x.png`,
/// `storage/avatars/y.png`) or full server URL to a valid image URL for the mobile app.
String resolveImageUrl(dynamic raw) {
  if (raw == null) return '';
  final value = raw.toString().trim();
  if (value.isEmpty) return '';

  final baseHost = ApiEndpoints.baseUrl.replaceFirst(
    RegExp(r'/api(?:/v1)?/?$'),
    '',
  );
  final baseUri = Uri.tryParse(baseHost);

  // If it's a full http(s) URL
  if (value.startsWith('http://') || value.startsWith('https://')) {
    final valUri = Uri.tryParse(value);
    if (baseUri != null && valUri != null && baseUri.hasAuthority) {
      final isLocalOrDev =
          valUri.host == 'localhost' ||
          valUri.host == '127.0.0.1' ||
          valUri.host.endsWith('.test') ||
          valUri.host.endsWith('.local') ||
          valUri.path.contains('/storage/') ||
          valUri.host != baseUri.host;

      if (isLocalOrDev) {
        final newPortStr = baseUri.hasPort ? ':${baseUri.port}' : '';
        final newOrigin = '${baseUri.scheme}://${baseUri.host}$newPortStr';
        final valPortStr = valUri.hasPort ? ':${valUri.port}' : '';
        final oldOrigin = '${valUri.scheme}://${valUri.host}$valPortStr';
        return value.replaceFirst(oldOrigin, newOrigin);
      }
    }
    return value;
  }

  // If it's a relative path
  String cleanPath = value.startsWith('/') ? value.substring(1) : value;
  if (cleanPath.startsWith('storage/')) {
    cleanPath = cleanPath.substring(8);
  }

  return '$baseHost/storage/$cleanPath';
}

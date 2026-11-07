import 'dart:convert';
import 'dart:typed_data';
import 'package:http/http.dart' as http;
import '../../../core/constants/api_endpoints.dart';
import '../../../core/exceptions/app_exception.dart';

/// Authenticated operations contract. No demo data or silent zero fallbacks.
class OperationsApi {
  final String? token;
  final http.Client _client;
  final bool _ownsClient;
  OperationsApi({required this.token, http.Client? client})
    : _client = client ?? http.Client(),
      _ownsClient = client == null;
  Map<String, String> get headers => {
    'Accept': 'application/json',
    if (token != null) 'Authorization': 'Bearer $token',
  };
  Uri uri(String path) => Uri.parse('${ApiEndpoints.baseUrl}/$path');
  Future<Map<String, dynamic>> get(String path) async => _decode(
    await _client
        .get(uri(path), headers: headers)
        .timeout(const Duration(seconds: 20)),
  );
  Future<Map<String, dynamic>> post(
    String path,
    Map<String, dynamic> data,
  ) async => _decode(
    await _client
        .post(
          uri(path),
          headers: {...headers, 'Content-Type': 'application/json'},
          body: jsonEncode(data),
        )
        .timeout(const Duration(seconds: 25)),
  );
  Future<Map<String, dynamic>> upload(
    String path,
    Map<String, dynamic> data, {
    Map<String, List<OperationsFile>> files = const {},
  }) async {
    final request = http.MultipartRequest('POST', uri(path))
      ..headers.addAll(headers);
    void flatten(String key, dynamic value) {
      if (value is Map) {
        value.forEach((k, v) => flatten('$key[$k]', v));
      } else if (value is List) {
        for (var i = 0; i < value.length; i++) {
          flatten('$key[$i]', value[i]);
        }
      } else if (value != null) {
        request.fields[key] = value is bool
            ? (value ? '1' : '0')
            : value.toString();
      }
    }

    data.forEach(flatten);
    for (final entry in files.entries) {
      for (var i = 0; i < entry.value.length; i++) {
        final file = entry.value[i];
        request.files.add(
          http.MultipartFile.fromBytes(
            entry.key.endsWith('[]') ? entry.key : entry.key,
            file.bytes,
            filename: file.name,
          ),
        );
      }
    }
    return _decode(
      await http.Response.fromStream(
        await _client.send(request).timeout(const Duration(seconds: 40)),
      ),
    );
  }

  Future<Uint8List> export(String report) async {
    final response = await _client
        .get(uri('operations/export?report=$report'), headers: headers)
        .timeout(const Duration(seconds: 60));
    if (response.statusCode != 200) {
      _decode(response);
    }
    if (response.bodyBytes.length < 2 ||
        response.bodyBytes[0] != 80 ||
        response.bodyBytes[1] != 75) {
      throw AppException(
        message: 'The server did not return a valid Excel workbook.',
      );
    }
    return response.bodyBytes;
  }

  Future<Uint8List> download(String path) async {
    final response = await _client
        .get(uri(path), headers: headers)
        .timeout(const Duration(seconds: 40));
    if (response.statusCode != 200) {
      _decode(response);
    }
    return response.bodyBytes;
  }

  static List<Map<String, dynamic>> rows(Map<String, dynamic> response) {
    dynamic data = response['data'];
    if (data is Map) {
      data = data['data'];
    }
    return data is List
        ? data
              .whereType<Map>()
              .map((e) => Map<String, dynamic>.from(e))
              .toList()
        : [];
  }

  Map<String, dynamic> _decode(http.Response response) {
    dynamic body;
    try {
      body = jsonDecode(response.body);
    } catch (_) {
      throw AppException(
        message:
            'The server returned an unreadable response. Please try again.',
      );
    }
    if (response.statusCode >= 200 &&
        response.statusCode < 300 &&
        body is Map) {
      return Map<String, dynamic>.from(body);
    }
    var message = body is Map ? body['message']?.toString() : null;
    if (body is Map && body['errors'] is Map) {
      final errors = (body['errors'] as Map).values;
      if (errors.isNotEmpty && errors.first is List) {
        message = (errors.first as List).first.toString();
      }
    }
    throw AppException(
      message: message ?? 'Unable to complete this request.',
      code: response.statusCode.toString(),
    );
  }

  void dispose() {
    if (_ownsClient) {
      _client.close();
    }
  }
}

class OperationsFile {
  final String name;
  final List<int> bytes;
  const OperationsFile(this.name, this.bytes);
}

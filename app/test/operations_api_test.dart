import 'dart:async';
import 'dart:convert';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:farmmantra/features/operations/data/operations_api.dart';
import 'package:farmmantra/services/api/http_api_service.dart';
import 'package:farmmantra/core/exceptions/app_exception.dart';

void main() {
  test(
    'operations requests use configured versioned API and bearer authentication',
    () async {
      final api = OperationsApi(
        token: 'session-token',
        client: MockClient((r) async {
          expect(r.url.path, endsWith('/api/v1/operations/requests'));
          expect(r.headers['Authorization'], 'Bearer session-token');
          expect(jsonDecode(r.body)['type'], 'funds');
          return http.Response('{"data":{"id":4,"status":"submitted"}}', 201);
        }),
      );
      final result = await api.post('operations/requests', {'type': 'funds'});
      expect(result['data']['status'], 'submitted');
    },
  );
  test(
    'validation and unreadable responses remain errors rather than empty success',
    () async {
      final api = OperationsApi(
        token: 'x',
        client: MockClient(
          (_) async => http.Response(
            '{"message":"Invalid","errors":{"location":["Outside assigned zone"]}}',
            422,
          ),
        ),
      );
      await expectLater(
        api.post('operations/requests', {}),
        throwsA(
          isA<AppException>().having(
            (e) => e.message,
            'message',
            'Outside assigned zone',
          ),
        ),
      );
      final broken = OperationsApi(
        token: 'x',
        client: MockClient(
          (_) async => http.Response('<html>Error</html>', 500),
        ),
      );
      await expectLater(
        broken.get('operations/dashboard'),
        throwsA(isA<AppException>()),
      );
    },
  );
  test(
    'multipart return preserves all line quantities and private photo uploads',
    () async {
      final api = OperationsApi(
        token: 'x',
        client: MockClient((request) async {
          expect(
            request.headers['content-type'],
            contains('multipart/form-data'),
          );
          expect(request.body, contains('items[0][product_id]'));
          expect(request.body, contains('photos[]'));
          expect(request.body, contains('transport'));
          return http.Response('{"data":{"id":7}}', 201);
        }),
      );
      final result = await api.upload(
        'operations/returns',
        {
          'order_id': 5,
          'transport': 'Pickup',
          'items': [
            {'product_id': 2, 'quantity': 1.5},
          ],
        },
        files: {
          'photos[]': [
            const OperationsFile('return.jpg', [1, 2, 3]),
          ],
        },
      );
      expect(result['data']['id'], 7);
    },
  );
  test('pagination unwraps Laravel direct and nested response shapes', () {
    expect(
      OperationsApi.rows({
        'data': [
          {'id': 1},
        ],
      }).single['id'],
      1,
    );
    expect(
      OperationsApi.rows({
        'data': {
          'data': [
            {'id': 2},
          ],
        },
      }).single['id'],
      2,
    );
  });
  test('simultaneous catalogue reads share one HTTP request', () async {
    final completer = Completer<http.Response>();
    var requests = 0;
    final api = HttpApiService(
      client: MockClient((_) async {
        requests++;
        return completer.future;
      }),
    );
    final first = api.getCategories();
    final second = api.getCategories();
    completer.complete(http.Response('{"data":[]}', 200));
    await Future.wait([first, second]);
    expect(requests, 1);
  });
  test('new server roles map without falling back to franchise', () async {
    for (final entry in {
      'Sales Head': 'salesHead',
      'Field Agronomist': 'fieldAgronomist',
    }.entries) {
      final api = HttpApiService(
        client: MockClient(
          (_) async => http.Response(
            jsonEncode({
              'token': 'x',
              'user': {
                'id': 1,
                'name': 'Role test',
                'email': 'role@test.local',
                'role': entry.key,
                'is_active': true,
              },
            }),
            200,
          ),
        ),
      );
      final result = await api.login('role@test.local', 'test');
      expect(result['user']['role'], entry.value);
    }
  });
}

import 'dart:convert';
import 'package:farmmantra/features/operations/data/operations_api.dart';
import 'package:farmmantra/features/operations/presentation/outlet_work.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:shared_preferences/shared_preferences.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();
  test(
    'network failure retains a count and reconnect retries only the signed-in user’s records',
    () async {
      SharedPreferences.setMockInitialValues({});
      var online = false;
      var accepted = 0;
      final api = OperationsApi(
        token: 'test',
        client: MockClient((request) async {
          if (!online) throw http.ClientException('offline');
          expect(request.headers['Authorization'], 'Bearer test');
          expect(request.body, contains('signed-snapshot'));
          accepted++;
          return http.Response('{"saved":true}', 200);
        }),
      );
      final prefs = await SharedPreferences.getInstance();
      final mine = '${CountOutbox.prefix(api, 'a')}1';
      final other = '${CountOutbox.prefix(api, 'b')}2';
      await prefs.setString(
        mine,
        jsonEncode({
          'snapshot': 'signed-snapshot',
          'lines': [
            {'count': 3},
          ],
        }),
      );
      await prefs.setString(other, '{}');
      await expectLater(
        CountOutbox.flush(api, 'a'),
        throwsA(isA<http.ClientException>()),
      );
      expect(prefs.containsKey(mine), isTrue);
      online = true;
      await CountOutbox.flush(api, 'a');
      await CountOutbox.flush(api, 'a');
      expect(accepted, 1);
      expect(prefs.containsKey(mine), isFalse);
      expect(prefs.containsKey(other), isTrue);
    },
  );
}

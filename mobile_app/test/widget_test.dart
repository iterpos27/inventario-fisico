import 'package:centro_ruliman_conteo/main.dart';
import 'package:centro_ruliman_conteo/services/api_client.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:shared_preferences/shared_preferences.dart';

void main() {
  testWidgets('shows login screen', (tester) async {
    SharedPreferences.setMockInitialValues({});
    final prefs = await SharedPreferences.getInstance();

    await tester.pumpWidget(ConteoApp(api: ApiClient(prefs, initialToken: '')));

    expect(find.text('Centro del Ruliman'), findsOneWidget);
    expect(find.text('Ingresar'), findsOneWidget);
  });
}

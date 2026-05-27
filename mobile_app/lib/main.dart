import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';

import 'screens/login_screen.dart';
import 'screens/tomas_screen.dart';
import 'services/api_client.dart';
import 'theme/app_theme.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();
  final prefs = await SharedPreferences.getInstance();
  final api = ApiClient(prefs);
  await api.loadToken();
  runApp(ConteoApp(api: api));
}

class ConteoApp extends StatelessWidget {
  const ConteoApp({super.key, required this.api});

  final ApiClient api;

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'Centro del Ruliman',
      debugShowCheckedModeBanner: false,
      theme: buildAppTheme(),
      home: api.hasToken ? TomasScreen(api: api) : LoginScreen(api: api),
    );
  }
}

import 'dart:convert';
import 'dart:io';

import 'package:http/http.dart' as http;

/// Every call the app makes to thamanmotorak.com.
///
/// The website's `api.php` was already a JSON API — `?do=submit`,
/// `?do=status`, `?do=support` — so the app speaks the *same* endpoints the
/// browser does. There is no second code path on the server to keep in step,
/// and a valuation sent from a phone lands in `requests.json` looking exactly
/// like one sent from a laptop.
class Api {
  Api({http.Client? client, this.base = defaultBase})
      : _http = client ?? http.Client();

  static const String defaultBase = 'https://www.thamanmotorak.com';

  final String base;
  final http.Client _http;

  Uri _u(String doVerb, [Map<String, String>? q]) =>
      Uri.parse('$base/api.php').replace(queryParameters: {'do': doVerb, ...?q});

  /// Everything the app needs to draw itself: the car database, the panel
  /// polygons, the condition options and every translated string.
  Future<Map<String, dynamic>> config() async {
    final r = await _http
        .get(_u('config'), headers: const {'Accept': 'application/json'})
        .timeout(const Duration(seconds: 20));
    return _decode(r);
  }

  /// Look a valuation up by its six-character code. No password — that is the
  /// website's design and the app must not invent a login the customer has
  /// never been given.
  Future<Map<String, dynamic>> status(String id) async {
    final r = await _http
        .get(_u('status', {'id': id.toUpperCase()}))
        .timeout(const Duration(seconds: 20));
    return _decode(r, allowError: true);
  }

  Future<Map<String, dynamic>> supportStatus(String id) async {
    final r = await _http
        .get(_u('support_status', {'id': id.toUpperCase()}))
        .timeout(const Duration(seconds: 20));
    return _decode(r, allowError: true);
  }

  Future<Map<String, dynamic>> support(Map<String, String> fields) async {
    final r = await _http
        .post(_u('support'), body: fields)
        .timeout(const Duration(seconds: 30));
    return _decode(r, allowError: true);
  }

  /// Submit a valuation.
  ///
  /// [photos] is keyed by slot — `front`, `back`, `left`… — which is exactly
  /// the field name `api.php` looks for (`photo_front`), so the server sorts
  /// the pictures into their positions without being told twice.
  ///
  /// [onProgress] reports bytes sent. Photos from a modern phone are several
  /// megabytes each and Qatari mobile coverage is not uniform; a customer who
  /// cannot see movement assumes the app has died and kills it half way.
  Future<Map<String, dynamic>> submit({
    required Map<String, String> fields,
    required Map<String, File> photos,
    List<File> videos = const [],
    void Function(int sent, int total)? onProgress,
  }) async {
    final req = http.MultipartRequest('POST', _u('submit'));
    req.fields.addAll(fields);

    for (final e in photos.entries) {
      req.files.add(await http.MultipartFile.fromPath('photo_${e.key}', e.value.path));
    }
    for (final v in videos) {
      req.files.add(await http.MultipartFile.fromPath('videos[]', v.path));
    }

    final total = req.contentLength;
    final streamed = await _http
        .send(_ProgressRequest(req, total, onProgress))
        .timeout(const Duration(minutes: 10));
    final r = await http.Response.fromStream(streamed);
    return _decode(r, allowError: true);
  }

  Map<String, dynamic> _decode(http.Response r, {bool allowError = false}) {
    Map<String, dynamic> body;
    try {
      body = jsonDecode(utf8.decode(r.bodyBytes)) as Map<String, dynamic>;
    } on Object {
      // A PHP fatal, a Hostinger error page, a captive portal — anything but
      // JSON. Say so plainly instead of throwing a parser error at the user.
      throw ApiException('bad_response', r.statusCode);
    }
    if (r.statusCode >= 400 && !allowError) {
      throw ApiException((body['error'] ?? 'http_${r.statusCode}').toString(), r.statusCode);
    }
    return body;
  }

  void close() => _http.close();
}

class ApiException implements Exception {
  ApiException(this.code, [this.status = 0]);
  final String code;
  final int status;
  @override
  String toString() => 'ApiException($code, $status)';
}

/// Wraps a multipart request so we can count the bytes as they leave.
///
/// The order inside [finalize] matters and is easy to get wrong: a
/// `MultipartRequest` only writes its own `content-type` header — the one
/// carrying the boundary — while it is being finalised. Copy the headers in the
/// constructor instead and the request goes out with no boundary at all, PHP
/// sees an empty `$_POST`, and the server answers "fields missing" for a form
/// that was filled in correctly.
class _ProgressRequest extends http.BaseRequest {
  _ProgressRequest(this._inner, this._total, this._onProgress)
      : super(_inner.method, _inner.url);

  final http.MultipartRequest _inner;
  final int _total;
  final void Function(int sent, int total)? _onProgress;

  @override
  int? get contentLength => _inner.contentLength;

  @override
  http.ByteStream finalize() {
    // 1. let the inner request build its body and set its content-type
    final source = _inner.finalize();
    // 2. take the finished headers, boundary included
    headers.addAll(_inner.headers);
    // 3. only now freeze ours
    super.finalize();

    var sent = 0;
    return http.ByteStream(
      source.map((chunk) {
        sent += chunk.length;
        _onProgress?.call(sent, _total);
        return chunk;
      }),
    );
  }
}

import '../../../core/utils/text_cleaner.dart';

int _int(dynamic value) => value is num ? value.toInt() : int.tryParse('${value ?? ''}') ?? 0;
double? _nullableDouble(dynamic value) => value == null ? null : (value is num ? value.toDouble() : double.tryParse('$value'));
DateTime? _date(dynamic value) {
  final raw = '${value ?? ''}'.trim();
  return raw.isEmpty ? null : DateTime.tryParse(raw)?.toLocal();
}

class SupportServiceItem {
  final String key;
  final String label;
  final bool configured;
  final bool operational;
  final String provider;
  final String message;
  const SupportServiceItem({required this.key, required this.label, required this.configured, required this.operational, required this.provider, required this.message});
  factory SupportServiceItem.fromJson(Map<String, dynamic> json) => SupportServiceItem(
        key: '${json['key'] ?? ''}',
        label: cleanOvanieText('${json['label'] ?? ''}'),
        configured: json['configured'] == true,
        operational: json['operational'] == true,
        provider: '${json['provider'] ?? ''}',
        message: cleanOvanieText('${json['message'] ?? ''}'),
      );
}

class SupportFaqItem {
  final int id;
  final String title;
  final String category;
  final String content;
  final DateTime? updatedAt;
  const SupportFaqItem({required this.id, required this.title, required this.category, required this.content, required this.updatedAt});
  factory SupportFaqItem.fromJson(Map<String, dynamic> json) => SupportFaqItem(
        id: _int(json['id']),
        title: cleanOvanieText('${json['title'] ?? ''}'),
        category: '${json['category'] ?? 'general'}',
        content: cleanOvanieText('${json['content'] ?? ''}'),
        updatedAt: _date(json['updated_at']),
      );
}

class SupportTicketSummary {
  final int id;
  final String reference;
  final String subject;
  final String category;
  final String priority;
  final String status;
  final String channel;
  final int? messagesCount;
  final DateTime? createdAt;
  final DateTime? updatedAt;
  const SupportTicketSummary({required this.id, required this.reference, required this.subject, required this.category, required this.priority, required this.status, required this.channel, required this.messagesCount, required this.createdAt, required this.updatedAt});
  factory SupportTicketSummary.fromJson(Map<String, dynamic> json) => SupportTicketSummary(
        id: _int(json['id']),
        reference: '${json['reference'] ?? ''}',
        subject: cleanOvanieText('${json['subject'] ?? ''}'),
        category: '${json['category'] ?? 'general'}',
        priority: '${json['priority'] ?? 'normal'}',
        status: '${json['status'] ?? 'open'}',
        channel: '${json['channel'] ?? ''}',
        messagesCount: json['messages_count'] == null ? null : _int(json['messages_count']),
        createdAt: _date(json['created_at']),
        updatedAt: _date(json['updated_at']),
      );
}

class SupportConversationSummary {
  final String token;
  final String subject;
  final String status;
  final String channel;
  final bool requiresHuman;
  final String agentName;
  final String ticketReference;
  final DateTime? lastMessageAt;
  const SupportConversationSummary({required this.token, required this.subject, required this.status, required this.channel, required this.requiresHuman, required this.agentName, required this.ticketReference, required this.lastMessageAt});
  factory SupportConversationSummary.fromJson(Map<String, dynamic> json) {
    final agent = json['agent'] is Map ? Map<String, dynamic>.from(json['agent'] as Map) : <String, dynamic>{};
    return SupportConversationSummary(
      token: '${json['token'] ?? ''}',
      subject: cleanOvanieText('${json['subject'] ?? 'Assistance OVANIE'}'),
      status: '${json['status'] ?? ''}',
      channel: '${json['channel'] ?? 'chat'}',
      requiresHuman: json['requires_human'] == true,
      agentName: cleanOvanieText('${agent['name'] ?? ''}'),
      ticketReference: '${json['ticket_reference'] ?? ''}',
      lastMessageAt: _date(json['last_message_at']),
    );
  }
}

class SupportCenterData {
  final List<SupportServiceItem> services;
  final String phone;
  final String phoneRateNotice;
  final bool whatsappAvailable;
  final String whatsappNumber;
  final String whatsappUrl;
  final List<SupportFaqItem> faqs;
  final List<SupportTicketSummary> tickets;
  final List<SupportConversationSummary> conversations;
  const SupportCenterData({required this.services, required this.phone, required this.phoneRateNotice, required this.whatsappAvailable, required this.whatsappNumber, required this.whatsappUrl, required this.faqs, required this.tickets, required this.conversations});
  factory SupportCenterData.fromJson(Map<String, dynamic> json) {
    List<T> parse<T>(String key, T Function(Map<String, dynamic>) builder) {
      final raw = json[key] is List ? json[key] as List : const [];
      return raw.whereType<Map>().map((e) => builder(Map<String, dynamic>.from(e))).toList(growable: false);
    }
    return SupportCenterData(
      services: parse('services', SupportServiceItem.fromJson),
      phone: '${json['phone'] ?? ''}',
      phoneRateNotice: cleanOvanieText('${json['phone_rate_notice'] ?? ''}'),
      whatsappAvailable: json['whatsapp_available'] == true,
      whatsappNumber: '${json['whatsapp_number'] ?? ''}'.trim(),
      whatsappUrl: '${json['whatsapp_url'] ?? ''}'.trim(),
      faqs: parse('faqs', SupportFaqItem.fromJson),
      tickets: parse('tickets', SupportTicketSummary.fromJson),
      conversations: parse('conversations', SupportConversationSummary.fromJson),
    );
  }
}

class SupportAttachment {
  final String name;
  final String mime;
  final int? size;
  final String url;
  final String downloadEndpoint;

  const SupportAttachment({required this.name, required this.mime, required this.size, required this.url, required this.downloadEndpoint});

  factory SupportAttachment.fromJson(dynamic value) {
    if (value is String) {
      return SupportAttachment(name: 'Pièce jointe', mime: '', size: null, url: value, downloadEndpoint: '');
    }
    final json = value is Map ? Map<String, dynamic>.from(value) : <String, dynamic>{};
    return SupportAttachment(
      name: cleanOvanieText('${json['name'] ?? 'Pièce jointe'}'),
      mime: '${json['mime'] ?? ''}',
      size: json['size'] == null ? null : _int(json['size']),
      url: '${json['url'] ?? ''}'.trim(),
      downloadEndpoint: '${json['download_endpoint'] ?? ''}'.trim(),
    );
  }
}

class SupportTicketMessageItem {
  final int id;
  final String authorType;
  final String body;
  final List<SupportAttachment> attachments;
  final DateTime? createdAt;
  const SupportTicketMessageItem({required this.id, required this.authorType, required this.body, required this.attachments, required this.createdAt});
  factory SupportTicketMessageItem.fromJson(Map<String, dynamic> json) {
    final raw = json['attachments'] is List ? json['attachments'] as List : const [];
    return SupportTicketMessageItem(
      id: _int(json['id']),
      authorType: '${json['author_type'] ?? ''}',
      body: cleanOvanieText('${json['body'] ?? ''}'),
      attachments: raw.map(SupportAttachment.fromJson).toList(growable: false),
      createdAt: _date(json['created_at']),
    );
  }
}

class SupportTicketDetail {
  final SupportTicketSummary summary;
  final String description;
  final int? orderId;
  final String orderNumber;
  final int? returnId;
  final String returnStatus;
  final double? refundAmount;
  final List<SupportTicketMessageItem> messages;
  const SupportTicketDetail({required this.summary, required this.description, required this.orderId, required this.orderNumber, required this.returnId, required this.returnStatus, required this.refundAmount, required this.messages});
  factory SupportTicketDetail.fromJson(Map<String, dynamic> json) {
    final order = json['order'] is Map ? Map<String, dynamic>.from(json['order'] as Map) : <String, dynamic>{};
    final ret = json['return'] is Map ? Map<String, dynamic>.from(json['return'] as Map) : <String, dynamic>{};
    final rawMessages = json['messages'] is List ? json['messages'] as List : const [];
    return SupportTicketDetail(
      summary: SupportTicketSummary.fromJson(json),
      description: cleanOvanieText('${json['description'] ?? ''}'),
      orderId: order.isEmpty ? null : _int(order['id']),
      orderNumber: '${order['order_number'] ?? ''}',
      returnId: ret.isEmpty ? null : _int(ret['id']),
      returnStatus: '${ret['status'] ?? ''}',
      refundAmount: _nullableDouble(ret['refund_amount']),
      messages: rawMessages.whereType<Map>().map((e) => SupportTicketMessageItem.fromJson(Map<String, dynamic>.from(e))).toList(growable: false),
    );
  }
}

class SupportChatMessage {
  final String sender;
  final String body;
  final DateTime? createdAt;
  const SupportChatMessage({required this.sender, required this.body, required this.createdAt});
  factory SupportChatMessage.fromJson(Map<String, dynamic> json) => SupportChatMessage(
        sender: '${json['sender'] ?? ''}',
        body: cleanOvanieText('${json['body'] ?? ''}'),
        createdAt: _date(json['created_at']),
      );
}

class SupportConversationDetail {
  final String status;
  final String agentName;
  final String ticketReference;
  final bool requiresHuman;
  final List<SupportChatMessage> messages;
  const SupportConversationDetail({required this.status, required this.agentName, required this.ticketReference, required this.requiresHuman, required this.messages});
  factory SupportConversationDetail.fromJson(Map<String, dynamic> json) {
    final agent = json['agent'] is Map ? Map<String, dynamic>.from(json['agent'] as Map) : <String, dynamic>{};
    final ticket = json['ticket'] is Map ? Map<String, dynamic>.from(json['ticket'] as Map) : <String, dynamic>{};
    final raw = json['messages'] is List ? json['messages'] as List : const [];
    return SupportConversationDetail(
      status: '${json['status'] ?? ''}',
      agentName: cleanOvanieText('${agent['name'] ?? ''}'),
      ticketReference: '${ticket['reference'] ?? ''}',
      requiresHuman: json['requires_human'] == true,
      messages: raw.whereType<Map>().map((e) => SupportChatMessage.fromJson(Map<String, dynamic>.from(e))).toList(growable: false),
    );
  }
}

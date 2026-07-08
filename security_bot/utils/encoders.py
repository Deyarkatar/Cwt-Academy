"""
Payload encoders for bypassing filters, WAFs, and input validation.
"""
from __future__ import annotations

import base64
import html
import urllib.parse


class PayloadEncoder:
    @staticmethod
    def url_encode(payload: str, double: bool = False) -> str:
        encoded = urllib.parse.quote(payload, safe="")
        return urllib.parse.quote(encoded, safe="") if double else encoded

    @staticmethod
    def html_encode(payload: str) -> str:
        return html.escape(payload)

    @staticmethod
    def base64_encode(payload: str) -> str:
        return base64.b64encode(payload.encode()).decode()

    @staticmethod
    def unicode_escape(payload: str) -> str:
        return "".join(f"\\u{ord(c):04x}" for c in payload)

    @staticmethod
    def hex_entities(payload: str) -> str:
        return "".join(f"&#x{ord(c):x};" for c in payload)

    @staticmethod
    def js_string_concat(payload: str) -> str:
        return "+".join(f"String.fromCharCode({ord(c)})" for c in payload)

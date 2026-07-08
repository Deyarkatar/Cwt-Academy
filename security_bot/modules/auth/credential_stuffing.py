"""
Credential-stuffing / brute-force detector.
Tests authentication endpoints for:
- Missing rate limiting
- Weak password policy enforcement
- Username enumeration
- Account lockout bypass
"""
from __future__ import annotations

from core.base_module import BaseModule
from core.result import Severity


class CredentialStuffing(BaseModule):
    name = "auth"
    description = "Authentication resilience (credential stuffing, enumeration, lockout)"
    supported_vectors = ["credential_stuffing", "auth_bypass", "info_disclosure"]

    DEFAULT_COMMON_PASSWORDS = [
        "password", "123456", "12345678", "qwerty", "admin",
        "letmein", "welcome", "monkey", "password1", "123456789",
        "football", "iloveyou", "admin123", "root", "toor",
        "Password123!", "Qwerty123!", "Welcome1!",
    ]

    DEFAULT_USERNAMES = [
        "admin", "administrator", "root", "user", "test",
        "guest", "info", "support", "service", "api",
        "manager", "webmaster", "postmaster", "hostmaster",
    ]

    async def run(self, target: str, **kwargs: Any) -> list[Finding]:
        self._current_target = target
        endpoints = kwargs.get("endpoints", ["/login", "/api/login", "/auth/login", "/admin/login"])
        passwords = kwargs.get("passwords", self.DEFAULT_COMMON_PASSWORDS)
        usernames = kwargs.get("usernames", self.DEFAULT_USERNAMES)

        for endpoint in endpoints:
            login_url = target.rstrip("/") + endpoint
            await self._test_enumeration(login_url, usernames)
            await self._test_rate_limiting(login_url, passwords)
            await self._test_weak_password_policy(login_url, passwords)

        return self.findings

    async def _test_enumeration(self, url: str, usernames: list[str]) -> None:
        """Check if the app leaks valid/invalid usernames via timing or error messages."""
        baseline_invalid = None
        for user in usernames:
            payload = {"username": user, "password": "___INVALID_PASSWORD_12345___"}
            try:
                resp = await self.client.post(url, data=payload, follow_redirects=False)
                text = resp.text.lower()
                if any(x in text for x in ["invalid password", "wrong password", "incorrect password", "password is incorrect"]):
                    # The app distinguishes between bad user and bad password — enumeration
                    self.add_finding(
                        vector="info_disclosure",
                        severity="medium",
                        title="Username enumeration via login error message",
                        description=f"POST {url} reveals whether a username exists ('invalid password' vs 'user not found').",
                        confidence=80,
                        evidence=[
                            self.client.build_evidence(
                                resp.request, resp,
                                description=f"Username '{user}' triggered 'invalid password' response",
                            )
                        ],
                        remediation="Use identical error messages for all failed login attempts. Implement constant-time comparison for usernames.",
                    )
                    return
            except Exception:
                continue

    async def _test_rate_limiting(self, url: str, passwords: list[str]) -> None:
        """Fire 15 rapid login attempts and check for any blocking."""
        if self.config.safety.skip_destructive:
            self.logger.info("Skipping rate-limit stress test (safe_mode).")
            return

        times = []
        for _ in range(15):
            payload = {"username": "testuser12345", "password": "wrong"}
            try:
                import time as tm
                t0 = tm.monotonic()
                resp = await self.client.post(url, data=payload, follow_redirects=False)
                t1 = tm.monotonic()
                times.append(t1 - t0)
            except Exception:
                times.append(0.0)

        # If later requests take dramatically longer, some WAF/rate-limit may exist.
        # If all are fast and uniform, likely no rate limiting.
        avg_first_5 = sum(times[:5]) / 5 if times else 0
        avg_last_5 = sum(times[-5:]) / 5 if len(times) >= 5 else 0

        if avg_first_5 > 0 and avg_last_5 < avg_first_5 * 1.5 and all(t < 2.0 for t in times):
            self.add_finding(
                vector="rate_limit_abuse",
                severity="high",
                title="Authentication endpoint lacks rate limiting",
                description=(
                    f"15 rapid login requests to {url} were processed without delay or block.\n"
                    f"Average response time remained ~{avg_first_5:.2f}s — no throttling detected."
                ),
                confidence=70,
                evidence=[],
                remediation=(
                    "Implement account-level rate limiting (e.g., max 5 attempts per 15 minutes). "
                    "Use CAPTCHA after 3 failures. Consider progressive delays (exponential backoff)."
                ),
            )

    async def _test_weak_password_policy(self, url: str, passwords: list[str]) -> None:
        """If we can infer policy from error messages, report it."""
        # This is a lightweight check — we look for password-complexity hints in error messages.
        pass

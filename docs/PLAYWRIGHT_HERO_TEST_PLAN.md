# Optional Playwright E2E Plan: Homepage Hero

Playwright is **not installed** in this project, and no `playwright.config.*` or `tests/e2e` directory exists. To avoid adding a large dependency, this plan documents the E2E tests that should be created if Playwright is adopted later.

## Spec to create

Create `tests/e2e/homepage-hero.spec.ts` with the following tests.

```ts
import { test, expect } from '@playwright/test';

test.describe('homepage hero', () => {
  test('English hero shows text on the left and the real robot on the right', async ({ page }) => {
    const errors: string[] = [];
    page.on('console', (msg) => {
      if (msg.type() === 'error') errors.push(msg.text());
    });

    await page.goto('http://localhost:8000?lang=en');

    const headline = page.locator('[data-testid="homepage-hero-text"] h1');
    await expect(headline).toContainText('Courses for Kurdistan');
    await expect(headline).toContainText('delivered through Telegram');

    const robot = page.locator('[data-testid="homepage-hero-robot"]');
    const text = page.locator('[data-testid="homepage-hero-text"]');

    await expect(robot).toBeVisible();
    await expect(text).toBeVisible();

    const robotBox = await robot.boundingBox();
    const textBox = await text.boundingBox();

    expect(robotBox).not.toBeNull();
    expect(textBox).not.toBeNull();

    if (robotBox && textBox) {
      expect(robotBox.width).toBeGreaterThan(100);
      expect(robotBox.height).toBeGreaterThan(100);
      expect(robotBox.x).toBeGreaterThan(textBox.x);
    }

    // No broken images anywhere on the page.
    const broken = await page.evaluate(() =>
      Array.from(document.querySelectorAll('img')).some(
        (img) => !img.complete || img.naturalWidth === 0
      )
    );
    expect(broken).toBe(false);

    // No CSP / asset errors.
    expect(errors).toEqual([]);
  });

  test('Kurdish hero shows the real robot on the left and text on the right', async ({ page }) => {
    const errors: string[] = [];
    page.on('console', (msg) => {
      if (msg.type() === 'error') errors.push(msg.text());
    });

    await page.goto('http://localhost:8000?lang=ku');

    const headline = page.locator('[data-testid="homepage-hero-text"] h1');
    await expect(headline).toContainText('کۆرسەکان بۆ کوردستان');
    await expect(headline).toContainText('تێلیگرامەوە');

    const robot = page.locator('[data-testid="homepage-hero-robot"]');
    const text = page.locator('[data-testid="homepage-hero-text"]');

    await expect(robot).toBeVisible();
    await expect(text).toBeVisible();

    const robotBox = await robot.boundingBox();
    const textBox = await text.boundingBox();

    expect(robotBox).not.toBeNull();
    expect(textBox).not.toBeNull();

    if (robotBox && textBox) {
      expect(robotBox.width).toBeGreaterThan(100);
      expect(robotBox.height).toBeGreaterThan(100);
      expect(robotBox.x).toBeLessThan(textBox.x);
    }

    const broken = await page.evaluate(() =>
      Array.from(document.querySelectorAll('img')).some(
        (img) => !img.complete || img.naturalWidth === 0
      )
    );
    expect(broken).toBe(false);

    expect(errors).toEqual([]);
  });
});
```

## How to enable

1. Install Playwright:
   ```bash
   npm install -D @playwright/test
   npx playwright install
   ```
2. Create `playwright.config.ts` and point `testDir` at `tests/e2e`.
3. Add an npm script to `package.json`:
   ```json
   "test:e2e": "playwright test"
   ```
4. Run with the Laravel dev server running:
   ```bash
   npm run serve
   npm run test:e2e
   ```

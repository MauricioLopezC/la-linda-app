import assert from 'node:assert/strict';
import test from 'node:test';
import { clampQuantity, nextQuantity, quantityLimit } from '../../resources/js/lib/ecommerce-quantity.ts';

test('caps the next increment at the actual fractional stock', () => {
  assert.equal(nextQuantity(1, 1, '1.250', true), 1.25);
  assert.equal(nextQuantity(1.25, 1, '1.250', true), 1.25);
});

test('reduces an insufficient cart line directly to the available maximum', () => {
  assert.equal(nextQuantity(3, -1, '1.000', false), 1);
  assert.equal(nextQuantity(2, -1, '0.100', true), 0.1);
});

test('supports stock smaller than the default selector step', () => {
  assert.equal(clampQuantity(1, '0.100', true), 0.1);
  assert.equal(clampQuantity(1, '0.001', true), 0.001);
  assert.equal(nextQuantity(0.1, -1, '0.100', true), 0.1);
});

test('keeps whole unit products integral and exhausted products at zero', () => {
  assert.equal(quantityLimit('1.250', false), 1);
  assert.equal(clampQuantity(1, '0.250', false), 0);
  assert.equal(clampQuantity(1, '0.000', true), 0);
});

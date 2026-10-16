export function quantityLimit(
  maximum: string,
  allowsDecimals: boolean,
): number {
  const thousandths = Math.max(0, Math.round(Number(maximum) * 1000));

  return allowsDecimals ? thousandths / 1000 : Math.floor(thousandths / 1000);
}

export function clampQuantity(
  quantity: number,
  maximum: string,
  allowsDecimals: boolean,
): number {
  const limit = Math.round(quantityLimit(maximum, allowsDecimals) * 1000);
  const minimum = Math.min(allowsDecimals ? 500 : 1000, limit);

  return Math.min(limit, Math.max(minimum, Math.round(quantity * 1000))) / 1000;
}

export function nextQuantity(
  current: number,
  delta: number,
  maximum: string,
  allowsDecimals: boolean,
): number {
  const step = allowsDecimals ? 500 : 1000;
  const next = (Math.round(current * 1000) + delta * step) / 1000;

  return clampQuantity(next, maximum, allowsDecimals);
}

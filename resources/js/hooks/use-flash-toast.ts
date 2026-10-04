import { router } from '@inertiajs/react';
import { useEffect, useRef } from 'react';
import { toast } from 'sonner';
import type { FlashToast } from '@/types/ui';

interface FlashPayload {
  success?: string | null;
  error?: string | null;
  warning?: string | null;
  info?: string | null;
  toast?: FlashToast | null;
}

export function useFlashToast(): void {
  const lastToastRef = useRef<string | null>(null);

  useEffect(() => {
    const handleFlash = (flash?: unknown) => {
      if (!flash || typeof flash !== 'object') {
        return;
      }

      const payload = flash as FlashPayload;

      if (payload.error && payload.error !== lastToastRef.current) {
        lastToastRef.current = payload.error;
        toast.error(payload.error);
      } else if (payload.success && payload.success !== lastToastRef.current) {
        lastToastRef.current = payload.success;
        toast.success(payload.success);
      } else if (payload.warning && payload.warning !== lastToastRef.current) {
        lastToastRef.current = payload.warning;
        toast.warning(payload.warning);
      } else if (payload.info && payload.info !== lastToastRef.current) {
        lastToastRef.current = payload.info;
        toast.info(payload.info);
      } else if (payload.toast) {
        toast[payload.toast.type](payload.toast.message);
      }
    };

    // Check initial page load on client
    if (typeof document !== 'undefined') {
      const appEl = document.getElementById('app');

      if (appEl?.dataset?.page) {
        try {
          const parsed = JSON.parse(appEl.dataset.page);
          handleFlash(parsed?.props?.flash);
        } catch {
          // Ignore JSON parse errors on invalid dataset
        }
      }
    }

    // Listen on navigation success for redirects with flash
    const removeSuccessListener = router.on('success', (event) => {
      handleFlash(event.detail.page.props.flash);
    });

    // Listen on custom flash events
    const removeFlashListener = router.on('flash', (event) => {
      const eventFlash = (event as CustomEvent).detail?.flash;
      const data = eventFlash?.toast as FlashToast | undefined;

      if (data) {
        toast[data.type](data.message);
      }
    });

    return () => {
      removeSuccessListener();
      removeFlashListener();
    };
  }, []);
}

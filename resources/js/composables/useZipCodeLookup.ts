import { ref } from 'vue';
import type { Ref } from 'vue';

export type ZipCodeAddress = {
    street: string;
    neighborhood: string;
    city: string;
    state: string;
};

type ViaCepResponse = {
    logradouro?: string;
    bairro?: string;
    localidade?: string;
    uf?: string;
    erro?: boolean | string;
};

export type UseZipCodeLookupReturn = {
    isLoading: Ref<boolean>;
    error: Ref<string | null>;
    lookup: (zipCode: string) => Promise<ZipCodeAddress | null>;
};

/**
 * Look up a Brazilian address by its CEP using the public ViaCEP API.
 * Only the latest lookup counts: a new one cancels the request still in flight.
 */
export function useZipCodeLookup(): UseZipCodeLookupReturn {
    const isLoading = ref(false);
    const error = ref<string | null>(null);
    let controller: AbortController | null = null;

    async function lookup(zipCode: string): Promise<ZipCodeAddress | null> {
        const digits = zipCode.replace(/\D/g, '');

        controller?.abort();
        error.value = null;

        if (digits.length !== 8) {
            isLoading.value = false;

            return null;
        }

        const currentController = new AbortController();
        controller = currentController;
        isLoading.value = true;

        try {
            const response = await fetch(
                `https://viacep.com.br/ws/${digits}/json/`,
                { signal: currentController.signal },
            );

            if (!response.ok) {
                throw new Error(`ViaCEP responded with ${response.status}`);
            }

            const data: ViaCepResponse = await response.json();

            if (data.erro) {
                error.value = 'CEP não encontrado.';

                return null;
            }

            return {
                street: data.logradouro ?? '',
                neighborhood: data.bairro ?? '',
                city: data.localidade ?? '',
                state: data.uf ?? '',
            };
        } catch (exception) {
            if (
                exception instanceof DOMException &&
                exception.name === 'AbortError'
            ) {
                return null;
            }

            error.value = 'Falha ao consultar o CEP.';

            return null;
        } finally {
            if (controller === currentController) {
                isLoading.value = false;
            }
        }
    }

    return { isLoading, error, lookup };
}

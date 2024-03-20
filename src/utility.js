import { appName } from './config.js';

const getT = (textToTranslate) => {
	return t(appName, textToTranslate);
}

export { getT };


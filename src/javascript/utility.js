import { appName, apiv1 } from './config.js';
import { generateOcsUrl, generateUrl } from '@nextcloud/router';
import { loadState } from '@nextcloud/initial-state';
import Vue from 'vue';

Vue.prototype.t = t;

let debugVueJs = false;

try {
	debugVueJs = loadState(appName, 'debugVueJs', false) === true;
	console.log(`App:${appName}-debug:${debugVueJs}`);
} catch (error) {
	debugVueJs = false;
}

export const setDebugVueJs = (enabled) => {
	debugVueJs = enabled === true;
	console.log(`App:${appName}-debug:${debugVueJs}`);
};

export const getBasename = (chosenFile) => {
	try {
		if (!chosenFile) {
			return '';
		}

		if (typeof chosenFile.basename !== 'undefined') {
			return chosenFile.basename ?? '';
		}

		return chosenFile._attributes?.basename ?? '';
	} catch (error) {
		console.error(error.message);
	}
};

export const CDEM = (payload) => {
	if (!payload || typeof payload !== 'object' || Array.isArray(payload)) {
		throw new Error('Payload is not a CDEM object');
	}

	const mandatoryKeys = ['code', 'data', 'error', 'message'];

	for (const key of mandatoryKeys) {
		if (!Object.prototype.hasOwnProperty.call(payload, key)) {
			throw new Error(`Payload is not a CDEM object: missing key "${key}"`);
		}
	}

	return {
		code: payload.code,
		data: payload.data,
		error: payload.error,
		message: payload.message,
	};
};

export const getAppUrl = (apiUrl) => {
	try {
		return generateUrl(`apps/${appName}${apiUrl}`);
	} catch (error) {
		log.error(`Exception: [${error.message}]`);
		return '';
	}
};

export const getOcsUrl = (apiUrl) => {
	try {
		return generateOcsUrl(`apps/${appName}${apiv1}${apiUrl}`);
	} catch (error) {
		log.error(`Exception: [${error.message}]`);
		return '';
	}
};

export const getT = (textToTranslate) => {
	let sentence = '';

	// Check if the text was an array before Json encoding
	if (isValidJSON(textToTranslate)) {
		let objTextToTranslate = JSON.parse(textToTranslate);

		if (Array.isArray(objTextToTranslate)) {
			// first item is the sentence to translate; the others are the parameters which will replave the %s in the original sentence
			// e.g. ["My message is %s at %s.", "short", "2024"] => "My message is short at 2024."
			// Warning : %s is the only varReplacement supported!
			sentence = t(appName, objTextToTranslate[0]);

			// Replace with parameters
			for (let index = 1; index < objTextToTranslate.length; index++) {
				sentence = sentence.replace('%s', objTextToTranslate[index]);
			}
		} else {
			sentence = t(appName, objTextToTranslate);
		}
	} else {
		sentence = t(appName, textToTranslate);
	}

	return sentence;
};

export const isEmail = (emailToCheck) => {
	try {
		var pattern = new RegExp(
			/^((([a-z]|\d|[!#\$%&'\*\+\-\/=\?\^_`{\|}~]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])+(\.([a-z]|\d|[!#\$%&'\*\+\-\/=\?\^_`{\|}~]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])+)*)|((\x22)((((\x20|\x09)*(\x0d\x0a))?(\x20|\x09)+)?(([\x01-\x08\x0b\x0c\x0e-\x1f\x7f]|\x21|[\x23-\x5b]|[\x5d-\x7e]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])|(\\([\x01-\x09\x0b\x0c\x0d-\x7f]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF]))))*(((\x20|\x09)*(\x0d\x0a))?(\x20|\x09)+)?(\x22)))@((([a-z]|\d|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])|(([a-z]|\d|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])([a-z]|\d|-|\.|_|~|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])*([a-z]|\d|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])))\.)+(([a-z]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])|(([a-z]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])([a-z]|\d|-|\.|_|~|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])*([a-z]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])))\.?$/i
		);
		return pattern.test(emailToCheck);
	} catch (error) {
		console.error(error.message);
	}
};

export const isEmailList = (listToCheck) => {
	try {
		// let formattedString = listToCheck.replace(/,/g, ";");
		let formattedString = listToCheck.replaceAll(new RegExp(/,/g), ';');
		// let formattedString = listToCheck.replaceAll(",", ";");
		let emailsToCheck = formattedString.split(';');

		let validEmails = 0;
		let scope = this;
		emailsToCheck.forEach(function (item, index) {
			if (!scope.isEmail(item)) {
				return false;
			} else {
				validEmails++;
			}
			return true;
		});

		if (emailsToCheck.length !== validEmails) {
			throw new Error('Invalid mail format');
		} else {
			return true;
		}
	} catch (error) {
		console.error(error.message);
		this.errorMessage = error.message;
		this.error = true;
	}
};

export const isEmptyString = (stringToCheck) => {
	try {
		return stringToCheck.trim().length === 0;
	} catch (error) {
		console.error(error.message);
		this.errorMessage = error.message;
		this.error = true;
	}
};

export const isEnabled = (parameterToCheck) => {
	try {
		switch (typeof parameterToCheck) {
			case typeof 666: //	an integer
				return parseInt(parameterToCheck) === 1;
				break;

			case typeof false:	// a boolean
				return parameterToCheck === true;
				break;

			default:
				throw new Error('This value has to be an integer or a boolean');
				break;
		}

	} catch (error) {
		return false;
	}
};

export const isFilledString = (stringToCheck) => {
	try {
		return !isEmptyString(stringToCheck);
	} catch (error) {
		return false;
	}
}

export const isIssueResponse = (response) => {
	try {
		return !isValidResponse(response);
	} catch (error) {
		return false;
	}
};

export const isNotEmail = (emailToCheck) => {
	try {
		return !isEmail(emailToCheck);
	} catch (error) {
		return false;
	}
}

export const isValidResponse = (response) => {
	try {
		if (!response.data) {
			throw new Error('Response data is missing');
		}

		if (!Object.prototype.hasOwnProperty.call(response.data, 'code')) {
			throw new Error('Response data code is missing');
		}

		return parseInt(response.data.code) === 0;

	} catch (error) {
		return false;
	}
};

export const isValidJSON = (str) => {
	try {
		JSON.parse(str);
		return true;
	} catch (e) {
		return false;
	}
};

export const getFunctionName = () => {
	try {
		const stack = new Error().stack;

		if (!stack) {
			return 'anonymous';
		}

		const stackLines = stack
			.split('\n')
			.map((line) => line.trim())
			.filter(Boolean);

		for (const callerLine of stackLines.slice(1)) {
			const functionMatch = callerLine.match(/^at\s+(?:async\s+)?(.+?)\s+\(/) ?? callerLine.match(/^at\s+(?:async\s+)?(.+)$/);

			if (!functionMatch) {
				continue;
			}

			let functionName = functionMatch[1].trim();

			if (
				functionName.startsWith('http://') ||
				functionName.startsWith('https://') ||
				functionName.startsWith('/')
			) {
				continue;
			}

			functionName = functionName.split('.').pop();

			if (functionName && functionName !== 'getFunctionName') {
				return functionName;
			}
		}

		return 'anonymous';
	} catch (error) {
		return 'anonymous';
	}
};

export const log = {
	debug: (message) => {
		if (debugVueJs) {
			console.debug(`DEBUG: ${message}`);
		}
	},
	info: (message) => {
		if (debugVueJs) {
			console.info(`INFO: ${message}`);
		}
	},
	warn: (message) => {
		console.warn(`WARN: ${message}`);
	},
	error: (message) => {
		console.error(`ERROR: ${message}`);
	}
};

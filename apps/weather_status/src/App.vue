<!--
  - SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div id="weather-status-menu-item">
		<NcActions class="weather-status-menu-item__subheader"
			:default-icon="weatherIcon"
			:aria-hidden="true"
			:aria-label="currentWeatherMessage"
			:menu-name="currentWeatherMessage">
			<NcActionText v-if="gotWeather"
				:aria-hidden="true"
				:icon="futureWeatherIcon">
				{{ forecastMessage }}
			</NcActionText>
			<NcActionLink v-if="gotWeather"
				icon="icon-address"
				target="_blank"
				:aria-hidden="true"
				:href="weatherLinkTarget"
				:close-after-click="true">
				{{ locationText }}
			</NcActionLink>
			<NcActionButton v-if="gotWeather"
				:aria-hidden="true"
				@click="onAddRemoveFavoriteClick">
				<template #icon>
					<component :is="addRemoveFavoriteIcon" :size="20" class="favorite-color" />
				</template>
				{{ addRemoveFavoriteText }}
			</NcActionButton>
			<NcActionSeparator v-if="address && !errorMessage" />
			<NcActionButton icon="icon-crosshair"
				:close-after-click="true"
				:aria-hidden="true"
				@click="onBrowserLocationClick">
				{{ t('weather_status', 'Detect location') }}
			</NcActionButton>
			<NcActionInput ref="addressInput"
				:label="t('weather_status', 'Set custom address')"
				:disabled="false"
				icon="icon-rename"
				:aria-hidden="true"
				type="text"
				value=""
				@submit="onAddressSubmit" />
			<template v-if="favorites.length > 0">
				<NcActionCaption :name="t('weather_status', 'Favorites')" />
				<NcActionButton v-for="favorite in favorites"
					:key="favorite"
					:aria-hidden="true"
					@click="onFavoriteClick($event, favorite)">
					<template #icon>
						<IconStar :size="20" :class="{'favorite-color': address === favorite}" />
					</template>
					{{ favorite }}
				</NcActionButton>
			</template>
		</NcActions>
	</div>
</template>

<script>
import { showError } from '@nextcloud/dialogs'
import moment from '@nextcloud/moment'
import { getLocale } from '@nextcloud/l10n'
import IconStar from 'vue-material-design-icons/Star.vue'
import IconStarOutline from 'vue-material-design-icons/StarOutline.vue'
import NcActions from '@nextcloud/vue/dist/Components/NcActions.js'
import NcActionButton from '@nextcloud/vue/dist/Components/NcActionButton.js'
import NcActionCaption from '@nextcloud/vue/dist/Components/NcActionCaption.js'
import NcActionInput from '@nextcloud/vue/dist/Components/NcActionInput.js'
import NcActionLink from '@nextcloud/vue/dist/Components/NcActionLink.js'
import NcActionSeparator from '@nextcloud/vue/dist/Components/NcActionSeparator.js'
import NcActionText from '@nextcloud/vue/dist/Components/NcActionText.js'
import * as network from './services/weatherStatusService.js'

const MODE_BROWSER_LOCATION = 1
const MODE_MANUAL_LOCATION = 2
const weatherOptions = {
	clearsky_day: {
		icon: 'icon-clearsky-day',
		text: (temperature, unit, later = false) => later
			? t('weather_status', '{temperature} {unit} clear sky later today', { temperature, unit })
			: t('weather_status', '{temperature} {unit} clear sky', { temperature, unit }),
	},
	clearsky_night: {
		icon: 'icon-clearsky-night',
		text: (temperature, unit, later = false) => later
			? t('weather_status', '{temperature} {unit} clear sky later today', { temperature, unit })
			: t('weather_status', '{temperature} {unit} clear sky', { temperature, unit }),
	},
	cloudy: {
		icon: 'icon-cloudy',
		text: (temperature, unit, later = false) => later
			? t('weather_status', '{temperature} {unit} cloudy later today', { temperature, unit })
			: t('weather_status', '{temperature} {unit} cloudy', { temperature, unit }),
	},
	fair_day: {
		icon: 'icon-fair-day',
		text: (temperature, unit, later = false) => later
			? t('weather_status', '{temperature} {unit} fair weather later today', { temperature, unit })
			: t('weather_status', '{temperature} {unit} fair weather', { temperature, unit }),
	},
	fair_night: {
		icon: 'icon-fair-night',
		text: (temperature, unit, later = false) => later
			? t('weather_status', '{temperature} {unit} fair weather later today', { temperature, unit })
			: t('weather_status', '{temperature} {unit} fair weather', { temperature, unit }),
	},
	partlycloudy_day: {
		icon: 'icon-partlycloudy-day',
		text: (temperature, unit, later = false) => later
			? t('weather_status', '{temperature} {unit} partly cloudy later today', { temperature, unit })
			: t('weather_status', '{temperature} {unit} partly cloudy', { temperature, unit }),
	},
	partlycloudy_night: {
		icon: 'icon-partlycloudy-night',
		text: (temperature, unit, later = false) => later
			? t('weather_status', '{temperature} {unit} partly cloudy later today', { temperature, unit })
			: t('weather_status', '{temperature} {unit} partly cloudy', { temperature, unit }),
	},
	fog: {
		icon: 'icon-fog',
		text: (temperature, unit, later = false) => later
			? t('weather_status', '{temperature} {unit} foggy later today', { temperature, unit })
			: t('weather_status', '{temperature} {unit} foggy', { temperature, unit }),
	},
	lightrain: {
		icon: 'icon-lightrain',
		text: (temperature, unit, later = false) => later
			? t('weather_status', '{temperature} {unit} light rainfall later today', { temperature, unit })
			: t('weather_status', '{temperature} {unit} light rainfall', { temperature, unit }),
	},
	rain: {
		icon: 'icon-rain',
		text: (temperature, unit, later = false) => later
			? t('weather_status', '{temperature} {unit} rainfall later today', { temperature, unit })
			: t('weather_status', '{temperature} {unit} rainfall', { temperature, unit }),
	},
	heavyrain: {
		icon: 'icon-heavyrain',
		text: (temperature, unit, later = false) => later
			? t('weather_status', '{temperature} {unit} heavy rainfall later today', { temperature, unit })
			: t('weather_status', '{temperature} {unit} heavy rainfall', { temperature, unit }),
	},
	rainshowers_day: {
		icon: 'icon-rainshowers-day',
		text: (temperature, unit, later = false) => later
			? t('weather_status', '{temperature} {unit} rainfall showers later today', { temperature, unit })
			: t('weather_status', '{temperature} {unit} rainfall showers', { temperature, unit }),
	},
	rainshowers_night: {
		icon: 'icon-rainshowers-night',
		text: (temperature, unit, later = false) => later
			? t('weather_status', '{temperature} {unit} rainfall showers later today', { temperature, unit })
			: t('weather_status', '{temperature} {unit} rainfall showers', { temperature, unit }),
	},
	lightrainshowers_day: {
		icon: 'icon-light-rainshowers-day',
		text: (temperature, unit, later = false) => later
			? t('weather_status', '{temperature} {unit} light rainfall showers later today', { temperature, unit })
			: t('weather_status', '{temperature} {unit} light rainfall showers', { temperature, unit }),
	},
	lightrainshowers_night: {
		icon: 'icon-light-rainshowers-night',
		text: (temperature, unit, later = false) => later
			? t('weather_status', '{temperature} {unit} light rainfall showers later today', { temperature, unit })
			: t('weather_status', '{temperature} {unit} light rainfall showers', { temperature, unit }),
	},
	heavyrainshowers_day: {
		icon: 'icon-heavy-rainshowers-day',
		text: (temperature, unit, later = false) => later
			? t('weather_status', '{temperature} {unit} heavy rainfall showers later today', { temperature, unit })
			: t('weather_status', '{temperature} {unit} heavy rainfall showers', { temperature, unit }),
	},
	heavyrainshowers_night: {
		icon: 'icon-heavy-rainshowers-night',
		text: (temperature, unit, later = false) => later
			? t('weather_status', '{temperature} {unit} heavy rainfall showers later today', { temperature, unit })
			: t('weather_status', '{temperature} {unit} heavy rainfall showers', { temperature, unit }),
	},
}

export default {
	name: 'App',
	components: {
		IconStar,
		NcActions,
		NcActionButton,
		NcActionCaption,
		NcActionInput,
		NcActionLink,
		NcActionSeparator,
		NcActionText,
	},
	data() {
		return {
			locale: getLocale(),
			loading: true,
			errorMessage: '',
			mode: MODE_BROWSER_LOCATION,
			address: null,
			lat: null,
			lon: null,
			// how many hours ahead do we want to see the forecast?
			offset: 5,
			forecasts: [],
			loop: null,
			favorites: [],
		}
	},
	computed: {
		useFahrenheitLocale() {
			return ['en_US', 'en_MH', 'en_FM', 'en_PW', 'en_KY', 'en_LR'].includes(this.locale)
		},
		temperatureUnit() {
			return this.useFahrenheitLocale ? '°F' : '°C'
		},
		locationText() {
			return t('weather_status', 'More weather for {adr}', { adr: this.address })
		},
		temperature() {
			return this.getTemperature(this.forecasts, 0)
		},
		futureTemperature() {
			return this.getTemperature(this.forecasts, this.offset)
		},
		weatherCode() {
			return this.getWeatherCode(this.forecasts, 0)
		},
		futureWeatherCode() {
			return this.getWeatherCode(this.forecasts, this.offset)
		},
		weatherIcon() {
			return this.getWeatherIcon(this.weatherCode, this.loading)
		},
		futureWeatherIcon() {
			return this.getWeatherIcon(this.futureWeatherCode, this.loading)
		},
		/**
		 * The message displayed in the top right corner
		 *
		 * @return {string}
		 */
		currentWeatherMessage() {
			if (this.loading) {
				return t('weather_status', 'Loading weather')
			} else if (this.errorMessage) {
				return this.errorMessage
			} else {
				return this.getWeatherMessage(this.weatherCode, this.temperature)
			}
		},
		forecastMessage() {
			if (this.loading) {
				return t('weather_status', 'Loading weather')
			} else {
				return this.getWeatherMessage(this.futureWeatherCode, this.futureTemperature, true)
			}
		},
		weatherLinkTarget() {
			return 'https://www.windy.com/-Rain-thunder-rain?rain,' + this.lat + ',' + this.lon + ',11'
		},
		gotWeather() {
			return this.address && !this.errorMessage
		},
		addRemoveFavoriteIcon() {
			return this.currentAddressIsFavorite
				? IconStar
				: IconStarOutline
		},
		addRemoveFavoriteText() {
			return this.currentAddressIsFavorite
				? t('weather_status', 'Remove from favorites')
				: t('weather_status', 'Add as favorite')
		},
		currentAddressIsFavorite() {
			return this.favorites.find((f) => {
				return f === this.address
			})
		},
	},
	mounted() {
		this.initWeatherStatus()
	},
	methods: {
		async initWeatherStatus() {
			try {
				const loc = await network.getLocation()
				this.lat = loc.lat
				this.lon = loc.lon
				this.address = loc.address
				this.mode = loc.mode

				if (this.mode === MODE_BROWSER_LOCATION) {
					this.askBrowserLocation()
				} else if (this.mode === MODE_MANUAL_LOCATION) {
					this.startLoop()
				}
				const favs = await network.getFavorites()
				this.favorites = favs
			} catch (err) {
				if (err?.code === 'ECONNABORTED') {
					console.info('The weather status request was cancelled because the user navigates.')
					return
				}
				if (err.response && err.response.status === 401) {
					showError(t('weather_status', 'You are not logged in.'))
				} else {
					showError(t('weather_status', 'There was an error getting the weather status information.'))
				}
				console.error(err)
			}
		},
		startLoop() {
			clearInterval(this.loop)
			if (this.lat && this.lon) {
				this.loop = setInterval(() => this.getForecast(), 60 * 1000 * 60)
				this.getForecast()
			} else {
				this.loading = false
			}
		},
		askBrowserLocation() {
			this.loading = true
			this.errorMessage = ''
			if (navigator.geolocation && window.isSecureContext) {
				navigator.geolocation.getCurrentPosition((position) => {
					console.debug('browser location success')
					this.lat = position.coords.latitude
					this.lon = position.coords.longitude
					this.saveMode(MODE_BROWSER_LOCATION)
					this.mode = MODE_BROWSER_LOCATION
					this.saveLocation(this.lat, this.lon)
				},
				(error) => {
					console.debug('location permission refused')
					console.debug(error)
					this.saveMode(MODE_MANUAL_LOCATION)
					this.mode = MODE_MANUAL_LOCATION
					// fallback on what we have if possible
					if (this.lat && this.lon) {
						this.startLoop()
					} else {
						this.usePersonalAddress()
					}
				})
			} else {
				console.debug('no secure context!')
				this.saveMode(MODE_MANUAL_LOCATION)
				this.mode = MODE_MANUAL_LOCATION
				this.startLoop()
			}
		},
		async getForecast() {
			try {
				this.forecasts = await network.fetchForecast()
			} catch (err) {
				this.errorMessage = t('weather_status', 'No weather information found')
				console.debug(err)
			}
			this.loading = false
		},
		async setAddress(address) {
			this.loading = true
			this.errorMessage = ''
			try {
				const loc = await network.setAddress(address)
				if (loc.success) {
					this.lat = loc.lat
					this.lon = loc.lon
					this.address = loc.address
					this.mode = MODE_MANUAL_LOCATION
					this.startLoop()
				} else {
					this.errorMessage = t('weather_status', 'Location not found')
					this.loading = false
				}
			} catch (err) {
				if (err.response && err.response.status === 401) {
					showError(t('weather_status', 'You are not logged in.'))
				} else {
					showError(t('weather_status', 'There was an error setting the location address.'))
				}
				this.loading = false
			}
		},
		async saveLocation(lat, lon) {
			try {
				const loc = await network.setLocation(lat, lon)
				this.address = loc.address
				this.startLoop()
			} catch (err) {
				if (err.response && err.response.status === 401) {
					showError(t('weather_status', 'You are not logged in.'))
				} else {
					showError(t('weather_status', 'There was an error setting the location.'))
				}
				console.debug(err)
			}
		},
		async saveMode(mode) {
			try {
				await network.setMode(mode)
			} catch (err) {
				if (err.response && err.response.status === 401) {
					showError(t('weather_status', 'You are not logged in.'))
				} else {
					showError(t('weather_status', 'There was an error saving the mode.'))
				}
				console.debug(err)
			}
		},
		onBrowserLocationClick() {
			this.askBrowserLocation()
		},
		async usePersonalAddress() {
			this.loading = true
			try {
				const loc = await network.usePersonalAddress()
				this.lat = loc.lat
				this.lon = loc.lon
				this.address = loc.address
				this.mode = MODE_MANUAL_LOCATION
				this.startLoop()
			} catch (err) {
				if (err.response && err.response.status === 401) {
					showError(t('weather_status', 'You are not logged in.'))
				} else {
					showError(t('weather_status', 'There was an error using personal address.'))
				}
				console.debug(err)
				this.loading = false
			}
		},
		onAddressSubmit() {
			const newAddress = this.$refs.addressInput.$el.querySelector('input[type="text"]').value
			this.setAddress(newAddress)
		},
		getLocalizedTemperature(celcius) {
			return this.useFahrenheitLocale
				? (celcius * (9 / 5)) + 32
				: celcius
		},
		onAddRemoveFavoriteClick() {
			const currentIsFavorite = this.currentAddressIsFavorite
			if (currentIsFavorite) {
				const i = this.favorites.indexOf(currentIsFavorite)
				if (i !== -1) {
					this.favorites.splice(i, 1)
				}
			} else {
				this.favorites.push(this.address)
			}
			network.saveFavorites(this.favorites)
		},
		onFavoriteClick(e, favAddress) {
			// clicked on the icon
			if (e.target.classList.contains('action-button__icon')) {
				const i = this.favorites.indexOf(favAddress)
				if (i !== -1) {
					this.favorites.splice(i, 1)
				}
				network.saveFavorites(this.favorites)
			} else if (favAddress !== this.address) {
				// clicked on the text
				this.setAddress(favAddress)
			}
		},
		formatTime(time) {
			return moment(time).format('LT')
		},
		getTemperature(forecasts, offset = 0) {
			return forecasts.length > offset ? forecasts[offset].data.instant.details.air_temperature : ''
		},
		getWeatherCode(forecasts, offset = 0) {
			return forecasts.length > offset ? forecasts[offset].data.next_1_hours.summary.symbol_code : ''
		},
		getWeatherIcon(weatherCode, loading) {
			if (loading) {
				return 'icon-loading-small'
			} else {
				return 'icon-weather ' + (weatherCode && weatherCode in weatherOptions
					? weatherOptions[weatherCode].icon
					: 'icon-fair-day')
			}
		},
		getWeatherMessage(weatherCode, temperature, later = false) {
			return weatherCode && weatherCode in weatherOptions
				? weatherOptions[weatherCode].text(
					Math.round(this.getLocalizedTemperature(temperature)),
					this.temperatureUnit,
					later,
				)
				: t('weather_status', 'Set location for weather')
		},
	},
}
</script>

<style lang="scss">
.icon-weather {
	background-size: 16px;
}
.icon-weather-status {
	background-image: url('./../img/app-dark.svg');
}
.icon-clearsky-day {
	background-image: url('./../img/sun.svg');
}
.icon-clearsky-night {
	background-image: url('./../img/moon.svg');
}
.icon-cloudy {
	background-image: url('./../img/cloud-cloud.svg');
}
.icon-fair-day {
	background-image: url('./../img/sun-small-cloud.svg');
}
.icon-fair-night {
	background-image: url('./../img/moon-small-cloud.svg');
}
.icon-partlycloudy-day {
	background-image: url('./../img/sun-cloud.svg');
}
.icon-partlycloudy-night {
	background-image: url('./../img/moon-cloud.svg');
}
.icon-fog {
	background-image: url('./../img/fog.svg');
}
.icon-lightrain {
	background-image: url('./../img/light-rain.svg');
}
.icon-rain {
	background-image: url('./../img/rain.svg');
}
.icon-heavyrain {
	background-image: url('./../img/heavy-rain.svg');
}
.icon-light-rainshowers-day {
	background-image: url('./../img/sun-cloud-light-rain.svg');
}
.icon-light-rainshowers-night {
	background-image: url('./../img/moon-cloud-light-rain.svg');
}
.icon-rainshowers-day {
	background-image: url('./../img/sun-cloud-rain.svg');
}
.icon-rainshowers-night {
	background-image: url('./../img/moon-cloud-rain.svg');
}
.icon-heavy-rainshowers-day {
	background-image: url('./../img/sun-cloud-heavy-rain.svg');
}
.icon-heavy-rainshowers-night {
	background-image: url('./../img/moon-cloud-heavy-rain.svg');
}
.icon-crosshair {
    background-color: var(--color-main-text);
    padding: 0 !important;
    mask: url(./../img/cross.svg) no-repeat;
    mask-size: 18px 18px;
    mask-position: center;
    -webkit-mask: url(./../img/cross.svg) no-repeat;
    -webkit-mask-size: 18px 18px;
    -webkit-mask-position: center;
    min-width: 44px !important;
    min-height: 44px !important;
}

// Set color to primary element for current / active favorite address
.favorite-color {
	color: var(--color-favorite);
}

.weather-status-menu-item__subheader {
	width: 100%;

	.trigger > .icon {
		background-size: 16px;
		border: 0;
		border-radius: var(--border-radius-pill);
		font-weight: normal;
		padding-inline-start: 40px;

		&.icon-loading-small {
			&::after {
				inset-inline-start: 21px;
			}
		}
	}
}
</style>

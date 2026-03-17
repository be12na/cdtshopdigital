import { Api, BaseApi } from 'boot/axios'
import { getRandomString } from 'src/utils'

const INITIAL_DATA_CACHE_KEY = '_cepatshop__initial_data'
const INITIAL_DATA_CACHE_TTL_MS = 5 * 60 * 1000

function readInitialDataCache() {
   try {
      const raw = localStorage.getItem(INITIAL_DATA_CACHE_KEY)
      if (!raw) return null
      const parsed = JSON.parse(raw)
      if (!parsed || typeof parsed !== 'object') return null
      if (!parsed.ts || !parsed.data) return null
      if (Date.now() - parsed.ts > INITIAL_DATA_CACHE_TTL_MS) return null
      return parsed.data
   } catch (e) {
      return null
   }
}

function writeInitialDataCache(data) {
   try {
      localStorage.setItem(INITIAL_DATA_CACHE_KEY, JSON.stringify({ ts: Date.now(), data }))
   } catch (e) {
      return
   }
}

export default {
   getAdminConfig({ commit }) {
      commit('SET_LOADING', true)
      BaseApi.get('config').then((response) => {
         if (response.status == 200) {
            this.commit('SET_CONFIG', response.data.data)
         }
      })
         .finally(() => {
            commit('SET_LOADING', false)
         })
   },
   flushData() {
      Api.get('clear-cache')
   },
   getAffiliateConfig({commit}) {
      Api.get('getAffiliateConfig').then(res => {
         commit('SET_AFFILIATE_CONFIG', res.data.data)
      })
   },
   generateSessionId({ commit }) {
      let result = getRandomString()
      commit('SET_SESSION_ID', result);
   },
   getShop: ({ commit }) => {
      Api.get('shop').then(response => {
         if (response.status == 200) {
            commit('SET_SHOP', response.data.data.shop)
            commit('SET_CONFIG', response.data.data.config)
         }
      })
   },
   getConfig: ({ commit }) => {
      Api.get('config').then(response => {
         if (response.status == 200) {
            let config = response.data.data
            commit('SET_CONFIG', config)
            commit('cart/SET_SERVICE_FEE', config, { root: true })
         }
      })
   },
   getInitialData: async ({ commit, state }, payload) => {
      const force = payload === true || payload?.force === true
      const background = payload?.background === true
      const hasBaseline = !!state.shop && !!state.config
      if (!state.session_id) {
         commit('SET_SESSION_ID', getRandomString())
      }

      if (!force) {
         const cached = readInitialDataCache()
         if (cached?.shop && cached?.config) {
            commit('SET_SHOP', cached.shop)
            commit('SET_CONFIG', cached.config)
            commit('front/SET_INITIAL_DATA', cached, { root: true })
            if (!background) {
               Api.get('getInitialData', { timeout: 2000 }).then((response) => {
                  if (response.status == 200) {
                     writeInitialDataCache(response.data.data)
                     commit('SET_SHOP', response.data.data.shop)
                     commit('SET_CONFIG', response.data.data.config)
                     commit('front/SET_INITIAL_DATA', response.data.data, { root: true })
                  }
               }).catch(() => { })
               return
            }
         }
      }

      const showLoading = !background && !hasBaseline
      if (showLoading) {
         document.body.classList.add('is_loading')
         commit('SET_LOADING', true)
      }

      try {
         let response = await Api.get('getInitialData', { timeout: 2000 })
         if (response.status == 200) {
            writeInitialDataCache(response.data.data)
            commit('SET_SHOP', response.data.data.shop)
            commit('SET_CONFIG', response.data.data.config)
            commit('front/SET_INITIAL_DATA', response.data.data, { root: true })
         }
      } finally {
         if (showLoading) {
            document.body.classList.remove('is_loading')
            commit('SET_LOADING', false)
         }
      }
   },
   dispatchEvent({ }, event) {
      setTimeout(() => {
         Api.post('dispatchEvent', { event: event })
      }, 2000)
   }
}

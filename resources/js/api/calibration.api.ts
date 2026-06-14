import axios from 'axios'

export const calibrationApi = {
  getStatus:  () => axios.get('/inventory/calibration/status'),
  getHistory: () => axios.get('/inventory/calibration/history'),
  getItems:   () => axios.get('/inventory/calibration/items'),
  apply:      (payload: { notes?: string; items: { id: number; qty_physical: number }[] }) =>
    axios.post('/inventory/calibration/apply', payload),
}

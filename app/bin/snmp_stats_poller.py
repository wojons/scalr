
import os
import sys
import multiprocessing
import MySQLdb
import MySQLdb.cursors
import time
import logging
import rrdtool
import ConfigParser
import threading
import Queue
import netsnmp

logging.basicConfig(stream=sys.stdout, level=logging.DEBUG)
LOG = logging.getLogger('snmp_stats_poller')

start_time = time.time()
exit_time = start_time

cpu_oids = {
		'user':'.1.3.6.1.4.1.2021.11.50.0',
		'nice':'.1.3.6.1.4.1.2021.11.51.0',
		'system':'.1.3.6.1.4.1.2021.11.52.0',
		'idle':'.1.3.6.1.4.1.2021.11.53.0'}

cpu_data_source = [
		'DS:user:COUNTER:600:U:U',
		'DS:system:COUNTER:600:U:U',
		'DS:nice:COUNTER:600:U:U',
		'DS:idle:COUNTER:600:U:U',]

cpu_archive = [
		'RRA:AVERAGE:0.5:1:800',
		'RRA:AVERAGE:0.5:6:800',
		'RRA:AVERAGE:0.5:24:800',
		'RRA:AVERAGE:0.5:288:800',
		'RRA:MAX:0.5:1:800',
		'RRA:MAX:0.5:6:800',
		'RRA:MAX:0.5:24:800',
		'RRA:MAX:0.5:288:800',
		'RRA:LAST:0.5:1:800',
		'RRA:LAST:0.5:6:800',
		'RRA:LAST:0.5:24:800',
		'RRA:LAST:0.5:288:800']

la_oids = {
		'la1':'.1.3.6.1.4.1.2021.10.1.3.1',
		'la5':'.1.3.6.1.4.1.2021.10.1.3.2',
		'la15':'.1.3.6.1.4.1.2021.10.1.3.3'}

la_data_source = [
		'DS:la1:GAUGE:600:U:U',
		'DS:la5:GAUGE:600:U:U',
		'DS:la15:GAUGE:600:U:U']

la_archive = [
		'RRA:AVERAGE:0.5:1:800',
		'RRA:AVERAGE:0.5:6:800',
		'RRA:AVERAGE:0.5:24:800',
		'RRA:AVERAGE:0.5:288:800',
		'RRA:MAX:0.5:1:800',
		'RRA:MAX:0.5:6:800',
		'RRA:MAX:0.5:24:800',
		'RRA:MAX:0.5:288:800',
		'RRA:LAST:0.5:1:800',
		'RRA:LAST:0.5:6:800',
		'RRA:LAST:0.5:24:800',
		'RRA:LAST:0.5:288:800']

mem_oids = {
		'swap':'.1.3.6.1.4.1.2021.4.3.0',
		'swapavail':'.1.3.6.1.4.1.2021.4.4.0',
		'total':'.1.3.6.1.4.1.2021.4.5.0',
		'avail':'.1.3.6.1.4.1.2021.4.6.0',
		'free':'.1.3.6.1.4.1.2021.4.11.0',
		'shared':'.1.3.6.1.4.1.2021.4.13.0',
		'buffer':'.1.3.6.1.4.1.2021.4.14.0',
		'cached':'.1.3.6.1.4.1.2021.4.15.0'}

mem_data_source = [
		'DS:swap:GAUGE:600:U:U',
		'DS:swapavail:GAUGE:600:U:U',
		'DS:total:GAUGE:600:U:U',
		'DS:avail:GAUGE:600:U:U',
		'DS:free:GAUGE:600:U:U',
		'DS:shared:GAUGE:600:U:U',
		'DS:buffer:GAUGE:600:U:U',
		'DS:cached:GAUGE:600:U:U']

mem_archive = [
		'RRA:AVERAGE:0.5:1:800',
		'RRA:AVERAGE:0.5:6:800',
		'RRA:AVERAGE:0.5:24:800',
		'RRA:AVERAGE:0.5:288:800',
		'RRA:MAX:0.5:1:800',
		'RRA:MAX:0.5:6:800',
		'RRA:MAX:0.5:24:800',
		'RRA:MAX:0.5:288:800',
		'RRA:LAST:0.5:1:800',
		'RRA:LAST:0.5:6:800',
		'RRA:LAST:0.5:24:800',
		'RRA:LAST:0.5:288:800']

net_oids = {
		'in':'.1.3.6.1.2.1.2.2.1.10.2',
		'out':'.1.3.6.1.2.1.2.2.1.16.2'}


net_data_source = [
		'DS:in:COUNTER:600:U:21474836480',
		'DS:out:COUNTER:600:U:21474836480']

net_archive = [
		'RRA:AVERAGE:0.5:1:800',
		'RRA:AVERAGE:0.5:6:800',
		'RRA:AVERAGE:0.5:24:800',
		'RRA:AVERAGE:0.5:288:800',
		'RRA:MAX:0.5:1:800',
		'RRA:MAX:0.5:6:800',
		'RRA:MAX:0.5:24:800',
		'RRA:MAX:0.5:288:800',
		'RRA:LAST:0.5:1:800',
		'RRA:LAST:0.5:6:800',
		'RRA:LAST:0.5:24:800',
		'RRA:LAST:0.5:288:800']

servers_num_source = [
		'DS:s_running:GAUGE:600:U:U']

servers_num_archive = [
		'RRA:AVERAGE:0.5:1:800',
		'RRA:AVERAGE:0.5:6:800',
		'RRA:AVERAGE:0.5:24:800',
		'RRA:AVERAGE:0.5:288:800',
		'RRA:MAX:0.5:1:800',
		'RRA:MAX:0.5:6:800',
		'RRA:MAX:0.5:24:800',
		'RRA:MAX:0.5:288:800',
		'RRA:LAST:0.5:1:800',
		'RRA:LAST:0.5:6:800',
		'RRA:LAST:0.5:24:800',
		'RRA:LAST:0.5:288:800']

BASE_DIR = os.path.abspath(os.path.abspath(__file__) + '/../..')
CONF = ConfigParser.ConfigParser()
CONF.read(BASE_DIR + '/etc/config.ini')

PID_FILE = '/var/run/scalr.snmp_stats.pid'

FARM_TASKS_QUEUE_SIZE = 1000 * 1
SNMP_TASKS_QUEUE_SIZE = 1000 * 10
RRD_TASKS_QUEUE_SIZE = 1000 * 20
FARM_WORKERS_NUM = 2
SNMP_WORKERS_NUM = 35
RRD_WORKERS_NUM = 5


class Watcher():
	def __init__(self, name, oids, source, archive):
		self.name = name
		self.oids = oids
		self.source = source
		self.archive = archive
		self.reverse_oids = dict((v, k) for k, v in self.oids.iteritems())
		self.source_type = dict((el.split(':')[1], el.split(':')[2]) for el in source)

snmp_watchers = [
		Watcher('cpu', cpu_oids, cpu_data_source, cpu_archive),
		Watcher('la', la_oids, la_data_source, la_archive),
		Watcher('mem', mem_oids, mem_data_source, mem_archive),
		Watcher('net', net_oids, net_data_source, net_archive)]


def _connect_db():
	for attempt in range(3):
		try:
			_db = dict((k, v.replace('"', '')) for k, v in CONF.items('db'))
			db = MySQLdb.connect(
					host=_db['host'], user=_db['user'],
					passwd=_db['pass'], db=_db['name'],
					cursorclass=MySQLdb.cursors.DictCursor)
			db.autocommit(True)
			LOG.debug("Connect to database")
			break
		except:
			if attempt == 2:
				LOG.error("Failed connect to database")
				db = None
			else:
				time.sleep(3)
				continue
	return db


farm_queue = multiprocessing.Queue(FARM_TASKS_QUEUE_SIZE)
snmp_queue = Queue.Queue()
rrd_queue = Queue.Queue()

def scheduller():
	#LOG.debug("Start scheduller")

	global farm_queue
	sql_farm_request =\
			"SELECT farms.id, farms.hash\
			FROM farms\
			INNER JOIN clients ON clients.id=farms.clientid\
			WHERE farms.status='1'\
			AND clients.status='Active'"

	db = _connect_db()
	if not db:
		LOG.error("Exit")
		raise SystemExit

	cur = db.cursor()

	for attempt in range(3):
		try:
			cur.execute(sql_farm_request)
			sql_farm_res = cur.fetchall()
			break
		except:
			if attempt == 2:
				LOG.error("Failed execute request: %s. Exit" %sql_farm_request)
				raise SystemExit
			time.sleep(1)
			cur.close()
			db = _connect_db()
			if not db:
				LOG.error("Exit")
				raise SystemExit
			cur = db.cursor()
			continue

	farm_hash_dict = dict((row['id'], row['hash']) for row in sql_farm_res)
	farms_id = farm_hash_dict.keys()
	if not farms_id:
		raise SystemExit

	sql_serv_request =\
			"SELECT servers.farm_id, servers.farm_roleid,\
			servers.server_id, servers.remote_ip, servers.index\
			FROM servers\
			WHERE servers.farm_id IN (%s)\
			AND servers.status='Running'\
			AND servers.remote_ip<>'None'" %','.join(map(str, farms_id))

	for attempt in range(3):
		try:
			cur.execute(sql_serv_request)
			sql_serv_res = cur.fetchall()
			break
		except:
			if attempt == 2:
				LOG.error("Failed execute request: %s. Exit" %sql_serv_request)
				raise SystemExit
			time.sleep(1)
			cur.close()
			db = _connect_db()
			if not db:
				LOG.error("Exit")
				raise SystemExit
			cur = db.cursor()
			continue

	servers_id = ["'%s'" %row['server_id'] for row in sql_serv_res]
	if not servers_id:
		return

	sql_snmp_port_request =\
			"SELECT server_id, value FROM server_properties\
			WHERE name='scalarizr.snmp_port' AND server_id IN (%s)"\
			%','.join(servers_id)

	for attempt in range(3):
		try:
			cur.execute(sql_snmp_port_request)
			serv_properties = cur.fetchall()
			break
		except:
			if attempt == 2:
				LOG.error("Failed execute request: %s. Exit" %sql_snmp_port_request)
				raise SystemExit
			time.sleep(1)
			cur.close()
			db = _connect_db()
			if not db:
				LOG.error("Exit")
				raise SystemExit
			cur = db.cursor()
			continue

	farms = {}
	for serv_row in sql_serv_res:
		port = 161
		for prop_row in serv_properties:
			if prop_row['server_id'] == serv_row['server_id']:
				port = prop_row['value']
				break

		farm_id = serv_row['farm_id']
		farm_role_id = serv_row['farm_roleid']
		community = farm_hash_dict[farm_id]
		host = serv_row['remote_ip']
		index = serv_row['index']

		farms.setdefault(farm_id, {}).setdefault(farm_role_id, []).append(\
				[index, community, host, port])

	farm_workers =\
			[multiprocessing.Process(target = FarmWorker(farm_queue), )
			for n in range(FARM_WORKERS_NUM)]

	for p in farm_workers:
		p.start()

	for farm in farms.items():
		farm_task = ['farm', farm]
		while True:
			try:
				farm_queue.put(farm_task, False)
				break
			except:
				time.sleep(1)
				continue

	while True:
		try:
			farm_queue.put(['done'], False)
			break
		except:
			time.sleep(1)
			continue

	for p in farm_workers:
		p.join()


class SNMPReader():
	def get_data(self, oids, host, port, community):
		session = netsnmp.Session(
			DestHost = '%s:%s' %(host, port),
			Version = 1,
			Community = community)
		Vars = netsnmp.VarList(*oids)
		return {oid:val for oid, val in zip(oids, session.get(Vars))}


class RRDWriter():
	def __init__(self, source, archive):
		self.source = source
		self.archive = archive

	def _create_db(self, rrd_db_path):
		if not os.path.exists(os.path.dirname(rrd_db_path)):
			os.makedirs(os.path.dirname(rrd_db_path))
		rrdtool.create(rrd_db_path, self.source, self.archive)

	def write(self, db_path, data):
		if not os.path.exists(db_path):
			self._create_db(db_path)
		#time_stamp = '%i' %(time.time())
		data_to_write = [data[s.split(':')[1]] for s in self.source]
		#val = time_stamp + ':' + ':'.join(map(str, data_to_write))
		val = 'N:' + ':'.join(map(str, data_to_write))
		try:
			#LOG.debug('%s, %s' %(db_path, val))
			rrdtool.update(db_path, "--daemon", "unix:/var/run/rrdcached.sock", val)
		except rrdtool.error, e:
			LOG.error('RRDTool update error:%s, %s' %(e, db_path))


ra = {}
fa = {}
rs = {}
fs = {}
class FarmWorker():
	terminate = multiprocessing.Value('i', 0)

	def __init__(self, task_queue):
		self.task_queue = task_queue

	def __call__(self):
		#LOG.debug("Start FarmWorker")
		global snmp_queue
		global rrd_queue

		rrd_workers = [RRDWorker() for i in range(RRD_WORKERS_NUM)]
		rrd_threads = [threading.Thread(target = w) for w in rrd_workers]
		for t in rrd_threads:
			t.start()

		snmp_workers = [SNMPWorker() for i in range(SNMP_WORKERS_NUM)]
		snmp_threads = [threading.Thread(target = w) for w in snmp_workers]
		for t in snmp_threads:
			t.start()

		while not FarmWorker.terminate.value or self.task_queue.qsize() != 0:
			try:
				task = self.task_queue.get(False)
			except:
				time.sleep(1)
				continue

			task_name = task[0]
			if task_name == 'farm':
				farm = task[1]
				farm_id = farm[0]
				roles = farm[1]
				for farm_role_id, servers in roles.iteritems():
					for serv in servers:
						index = serv[0]
						community = serv[1]
						host = serv[2]
						port = serv[3]
						while True:
							if FARM_WORKERS_NUM > 1 and snmp_queue.qsize() > 300:
								time.sleep(2)
								continue
							try:
								snmp_queue.put(['server', farm_id, farm_role_id,\
										index, community, host, port], False)
								break
							except:
								time.sleep(1)
								continue
			elif task_name == 'done':
				FarmWorker.terminate.value = 1
		while True:
			try:
				snmp_queue.put(['done'], False)
				break
			except:
				time.sleep(1)
				continue

		for t in snmp_threads:
			t.join()

		snmp_join_time = time.time() - start_time
		#LOG.debug('SNMP join time: %s' %snmp_join_time)

		#put ra into rrd_queue
		for k, oid_names in ra.iteritems():
			k_split = k.split('/')
			data = {}
			for oid_name, v in oid_names.iteritems():
				if v[1] is not 'U':
					if type(v[1]) is int:
						data.update({oid_name:'%i' %(v[1]/v[0])})
					elif type(v[1]) is float:
						data.update({oid_name:'%.2f' %(v[1]/v[0])})
				else:
					data.update({oid_name:v[1]})
			while True:
				try:
					rrd_queue.put(['ra', k_split[0], k_split[1], k_split[2], data], False)
					break
				except:
					time.sleep(1)
					continue

		#put fa into rrd_queue
		for k, oid_names in fa.iteritems():
			k_split = k.split('/')
			data = {}
			for oid_name, v in oid_names.iteritems():
				if v[1] is not 'U':
					if type(v[1]) is int:
						data.update({oid_name:'%i' %(v[1]/v[0])})
					elif type(v[1]) is float:
						data.update({oid_name:'%.2f' %(v[1]/v[0])})
				else:
					data.update({oid_name:v[1]})
			while True:
				try:
					rrd_queue.put(['fa', k_split[0], k_split[1], data], False)
					break
				except:
					time.sleep(1)
					continue

		#put rs into rrd_queue
		for k, v in rs.iteritems():
			k_split = k.split('/')
			while True:
				try:
					rrd_queue.put(['rs', k_split[0], k_split[1], k_split[2], v], False)
					break
				except:
					time.sleep(1)
					continue

		#put fs into rrd_queue
		for k, v in fs.iteritems():
			k_split = k.split('/')
			while True:
				try:
					rrd_queue.put(['fs', k_split[0], k_split[1], v], False)
					break
				except:
					time.sleep(1)
					continue

		while True:
			try:
				rrd_queue.put(['done'], False)
				break
			except:
				time.sleep(1)
				continue

		for t in rrd_threads:
			t.join()
		#LOG.debug('rrd join')


oids = [el for w in snmp_watchers for el in w.oids.values()]
snmp_reader = SNMPReader()
rrd_writers = dict((w.name, RRDWriter(w.source, w.archive)) for w in snmp_watchers)
rrd_writers.update({'ServersNum':RRDWriter(servers_num_source, servers_num_archive)})


class SNMPWorker():
	terminate = False

	def __call__(self):
		#LOG.debug("Start SNMPWorker")
		global snmp_queue
		global rrd_queue
		global ra
		global rs
		global fa
		global fs

		while not SNMPWorker.terminate or snmp_queue.qsize() != 0:
			try:
				snmp_task = snmp_queue.get(False)
			except:
				time.sleep(1)
				continue

			task_name = snmp_task[0]
			if task_name == 'server':
				farm_id = snmp_task[1]
				farm_role_id = snmp_task[2]
				index = snmp_task[3]
				community = snmp_task[4]
				host = snmp_task[5]
				port = snmp_task[6]

				rs_key = '%s/%s/ServersNum' %(farm_id, farm_role_id)
				fs_key = '%s/ServersNum' %farm_id
				rs.setdefault(rs_key, {}).setdefault('s_running', 0)
				fs.setdefault(fs_key, {}).setdefault('s_running', 0)
				rs[rs_key]['s_running'] += 1
				fs[fs_key]['s_running'] += 1

				snmp_data = snmp_reader.get_data(oids, host, port, community)
				if snmp_data:
					for w in snmp_watchers:
						data = {}
						for oid, oid_value in snmp_data.iteritems():
							if oid in w.reverse_oids:
								ra_key = '%s/%s/%s' %(farm_id, farm_role_id, w.name)
								fa_key = '%s/%s' %(farm_id, w.name)
								oid_name = w.reverse_oids[oid]
								ra.setdefault(ra_key, {}).setdefault(oid_name, [0, 'U'])
								fa.setdefault(fa_key, {}).setdefault(oid_name, [0, 'U'])
								if oid_value == None or oid_value == 'U':
									oid_value = 'U'
								else:
									#calc ra
									if ra[ra_key][oid_name][1] == 'U':
										ra[ra_key][oid_name][1] = 0
									if w.source_type[oid_name] == 'COUNTER':
										ra[ra_key][oid_name][1] = ra[ra_key][oid_name][1] + int(oid_value)
									elif w.source_type[oid_name] == 'GAUGE':
										ra[ra_key][oid_name][1] = ra[ra_key][oid_name][1] + float(oid_value)
									ra[ra_key][oid_name][0] += 1

									#calc fa
									if fa[fa_key][oid_name][1] == 'U':
										fa[fa_key][oid_name][1] = 0
									if w.source_type[oid_name] == 'COUNTER':
										fa[fa_key][oid_name][1] = fa[fa_key][oid_name][1] + int(oid_value)
									elif w.source_type[oid_name] == 'GAUGE':
										fa[fa_key][oid_name][1] = fa[fa_key][oid_name][1] + float(oid_value)
									fa[fa_key][oid_name][0] += 1

								data.update({oid_name:oid_value})
						if data:
							rrd_task = ['server', farm_id, farm_role_id, index, w.name, data]
							while True:
								try:
									rrd_queue.put(rrd_task, False)
									break
								except:
									time.sleep(1)
									continue
					#LOG.debug("snmp processed: %s" %host)
			elif task_name == 'done':
				SNMPWorker.terminate = True
		#LOG.debug('Exit SNMPWorker')


rrd_path = CONF.get('statistics', 'rrd_db_dir').replace('"', '')
class RRDWorker(object):
	terminate = False

	def __call__(self):
		#LOG.debug("Start RRDWorker")
		global rrd_queue

		while not RRDWorker.terminate or rrd_queue.qsize() != 0:
			try:
				rrd_task = rrd_queue.get(False)
			except:
				time.sleep(1)
				continue

			task_name = rrd_task[0]
			if task_name == 'server':
				farm_id = rrd_task[1]
				farm_role_id = rrd_task[2]
				index = rrd_task[3]
				w_name = rrd_task[4]
				data = rrd_task[5]
				i = int(str(farm_id)[-1])-1
				x1 = str(i-5*(i/5)+1)[-1]
				x2 = str(i-5*(i/5)+6)[-1]
				rrd_writers[w_name].write('%s/x%sx%s/%s/INSTANCE_%s_%s/%sSNMP/db.rrd'\
						%(rrd_path, x1, x2, farm_id, farm_role_id, index, w_name.upper()), data)
			elif task_name == 'ra':
				farm_id = rrd_task[1]
				farm_role_id = rrd_task[2]
				w_name = rrd_task[3]
				data = rrd_task[4]
				i = int(str(farm_id)[-1])-1
				x1 = str(i-5*(i/5)+1)[-1]
				x2 = str(i-5*(i/5)+6)[-1]
				rrd_writers[w_name].write('%s/x%sx%s/%s/FR_%s/%sSNMP/db.rrd'\
						%(rrd_path, x1, x2, farm_id, farm_role_id, w_name.upper()), data)
			elif task_name == 'fa':
				farm_id = rrd_task[1]
				w_name = rrd_task[2]
				data = rrd_task[3]
				i = int(str(farm_id)[-1])-1
				x1 = str(i-5*(i/5)+1)[-1]
				x2 = str(i-5*(i/5)+6)[-1]
				rrd_writers[w_name].write('%s/x%sx%s/%s/FARM/%sSNMP/db.rrd'\
						%(rrd_path, x1, x2, farm_id, w_name.upper()), data)
			elif task_name == 'rs':
				farm_id = rrd_task[1]
				farm_role_id = rrd_task[2]
				w_name = rrd_task[3]
				data = rrd_task[4]
				i = int(str(farm_id)[-1])-1
				x1 = str(i-5*(i/5)+1)[-1]
				x2 = str(i-5*(i/5)+6)[-1]
				rrd_writers[w_name].write('%s/x%sx%s/%s/FR_%s/SERVERS/db.rrd'\
						%(rrd_path, x1, x2, farm_id, farm_role_id), data)
			elif task_name == 'fs':
				farm_id = rrd_task[1]
				w_name = rrd_task[2]
				data = rrd_task[3]
				i = int(str(farm_id)[-1])-1
				x1 = str(i-5*(i/5)+1)[-1]
				x2 = str(i-5*(i/5)+6)[-1]
				rrd_writers[w_name].write('%s/x%sx%s/%s/FARM/SERVERS/db.rrd'\
					%(rrd_path, x1, x2, farm_id), data)
			elif task_name == 'done':
				RRDWorker.terminate = True
		#LOG.debug('Exit RRDWorker')


def main():
	#LOG.debug('Start main')

	if os.path.exists(PID_FILE):
		pid = open(PID_FILE).read().strip()
		if os.path.exists('/proc/' + pid):
			LOG.info('Another copy already running (pid: %s)', pid)
			raise SystemExit
	with open(PID_FILE, 'w+') as fp:
		fp.write(str(os.getpid()))

	scheduller()

	global exit_time
	exit_time = time.time() - start_time
	LOG.info('Exit: %s' %exit_time)


if __name__ == '__main__':
	main()

import os
import pytz
import time
import rrdtool
import logging
import datetime

from scalrpy.util import helper


LOG = logging.getLogger('ScalrPy')

SOURCE = {
    'cpu': [
        'DS:user:COUNTER:600:U:U',
        'DS:system:COUNTER:600:U:U',
        'DS:nice:COUNTER:600:U:U',
        'DS:idle:COUNTER:600:U:U',
        ],
    'la': [
        'DS:la1:GAUGE:600:U:U',
        'DS:la5:GAUGE:600:U:U',
        'DS:la15:GAUGE:600:U:U',
        ],
    'mem': [
        'DS:swap:GAUGE:600:U:U',
        'DS:swapavail:GAUGE:600:U:U',
        'DS:total:GAUGE:600:U:U',
        'DS:avail:GAUGE:600:U:U',
        'DS:free:GAUGE:600:U:U',
        'DS:shared:GAUGE:600:U:U',
        'DS:buffer:GAUGE:600:U:U',
        'DS:cached:GAUGE:600:U:U',
        ],
    'net': [
        'DS:in:COUNTER:600:U:21474836480',
        'DS:out:COUNTER:600:U:21474836480',
        ],
    'snum': [
        'DS:s_running:GAUGE:600:U:U'],
    'io': [
        'DS:read:COUNTER:600:U:U',
        'DS:write:COUNTER:600:U:U',
        'DS:rbyte:COUNTER:600:U:U',
        'DS:wbyte:COUNTER:600:U:U'],
    }

ARCHIVE = {
    'cpu': [
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
        'RRA:LAST:0.5:288:800',
        ],
    'la': [
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
        'RRA:LAST:0.5:288:800',
        ],
    'mem': [
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
        'RRA:LAST:0.5:288:800',
        ],
    'net': [
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
        'RRA:LAST:0.5:288:800',
        ],
    'snum': [
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
        'RRA:LAST:0.5:288:800',
        ],
    'io': [
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
        'RRA:LAST:0.5:288:800',
        ],
    }

GRAPH_OPT = {
    'daily':{
        'start':'-1d5min',
        'end':'-5min',
        'step':'180',
        'update_every':'600',
        'x_grid':'HOUR:1:HOUR:2:HOUR:2:0:%H',
        },
    'weekly':{
        'start':'-1wk5min',
        'end':'-5min',
        'step':'1800',
        'update_every':'7200',
        'x_grid':'HOUR:12:HOUR:24:HOUR:24:0:%a',
        },
    'monthly':{
        'start':'-1mon5min',
        'end':'-5min',
        'step':'7200',
        'update_every':'43200',
        'x_grid':'DAY:2:WEEK:1:WEEK:1:0:week %V',
        },
    'yearly':{
        'start':'-1y',
        'end':'-5min',
        'step':'86400',
        'update_every':'86400',
        'x_grid':'MONTH:1:MONTH:1:MONTH:1:0:%b',
        },
    }


def create_db(file_path, metric):
    if not os.path.exists(os.path.dirname(file_path)):
        os.makedirs(os.path.dirname(file_path))
    rrdtool.create(file_path, SOURCE[metric], ARCHIVE[metric])


def get_data_to_write(metric_name, metric_data):
    data_to_write = 'N'
    for source in SOURCE[metric_name]:
        data_type = {'COUNTER': int, 'GAUGE': float}[source.split(':')[2]]
        try:
            data_to_write += ':%s' % (data_type)(metric_data[source.split(':')[1]])
        except:
            data_to_write += ':U'
    return data_to_write


def update(file_path, data):
    LOG.debug('%s, %s, %s' % (time.time(), file_path, data))
    try:
        rrdtool.update(file_path, '--daemon', 'unix:/var/run/rrdcached.sock', data)
    except rrdtool.error:
        LOG.error('%s rrdtool update error: %s' % (file_path, helper.exc_info()))


def write(base_dir, data):
    try:
        for metric_name, metric_data in data.iteritems():
            if metric_name == 'snum':
                file_path = os.path.join(base_dir, 'SERVERS', 'db.rrd')
                if not os.path.isfile(file_path):
                    create_db(file_path, metric_name)
                data_to_write = get_data_to_write(metric_name, metric_data)
                update(file_path, data_to_write)
            elif metric_name == 'io':
                for device_name, device_data in metric_data.iteritems():
                    file_path = os.path.join(base_dir, 'IO', '%s.rrd' % device_name)
                    if not os.path.isfile(file_path):
                        create_db(file_path, metric_name)
                    data_to_write = get_data_to_write(metric_name, device_data)
                    update(file_path, data_to_write)
            else:
                name_upper = metric_name.upper()
                file_path = os.path.join(base_dir, '%sSNMP' % name_upper, 'db.rrd')
                if not os.path.isfile(file_path):
                    create_db(file_path, metric_name)
                data_to_write = get_data_to_write(metric_name, metric_data)
                update(file_path, data_to_write)
    except:
        LOG.error(helper.exc_info())


def plot_cpu(img_path, rrd_path, opt, tz=None):
    color_cpu_user_line = '#00DF00FF'
    color_cpu_user_area = '#00DF0044'
    color_cpu_syst_line = '#DF0000FF'
    color_cpu_syst_area = '#DF000044'
    color_cpu_nice_line = '#DFDF00FF'
    color_cpu_nice_area = '#DFDF0044'
    color_cpu_idle_line = '#0051AAFF'
    color_cpu_idle_area = '#0051AA22'
    if tz:
        utc = datetime.datetime.utcnow()
        time_string = pytz.timezone(tz).fromutc(utc).strftime('%b %d, %Y %H:%M:%S %z')
    else:
        time_string = time.strftime('%b %d, %Y %H:%M:%S %z')
    rrdtool.graph(
            img_path,
            '--imgformat', 'PNG',
            '--step', opt['step'],
            '--pango-markup',
            '--vertical-label', 'Percent CPU Utilization',
            '--title', 'CPU Utilization (%s)' % time_string,
            '--upper-limit', '100',
            '--alt-autoscale-max',
            '--alt-autoscale-min',
            '--rigid',
            '--no-gridfit',
            '--slope-mode',
            '--x-grid', opt['x_grid'],
            '--end', opt['end'],
            '--start', opt['start'],
            '--width', '440',
            '--height', '160',
            '--font-render-mode', 'normal',
            '--border', '0',
            '--color', 'BACK#FFFFFF',
            'DEF:a=%s:user:AVERAGE' % rrd_path,
            'DEF:b=%s:system:AVERAGE' % rrd_path,
            'DEF:c=%s:nice:AVERAGE' % rrd_path,
            'DEF:d=%s:idle:AVERAGE' % rrd_path,
            'CDEF:total=a,b,c,d,+,+,+',
            'CDEF:a_perc=a,total,/,100,*',
            'VDEF:a_perc_last=a_perc,LAST',
            'VDEF:a_perc_avg=a_perc,AVERAGE',
            'VDEF:a_perc_max=a_perc,MAXIMUM',
            'CDEF:b_perc=b,total,/,100,*',
            'VDEF:b_perc_last=b_perc,LAST',
            'VDEF:b_perc_avg=b_perc,AVERAGE',
            'VDEF:b_perc_max=b_perc,MAXIMUM',
            'CDEF:c_perc=c,total,/,100,*',
            'VDEF:c_perc_last=c_perc,LAST',
            'VDEF:c_perc_avg=c_perc,AVERAGE',
            'VDEF:c_perc_max=c_perc,MAXIMUM',
            'CDEF:d_perc=d,total,/,100,*',
            'VDEF:d_perc_last=d_perc,LAST',
            'VDEF:d_perc_avg=d_perc,AVERAGE',
            'VDEF:d_perc_max=d_perc,MAXIMUM',

            'COMMENT:<b><tt>                Current   Average   Maximum</tt></b>\l',

            'LINE1:a_perc%s:<tt>user  </tt>\\t' % color_cpu_user_line,
            'AREA:a_perc%s:' % color_cpu_user_area,
            'GPRINT:a_perc_last:<tt>%3.0lf%% </tt>\\t',
            'GPRINT:a_perc_avg:<tt>%3.0lf%% </tt>\\t',
            'GPRINT:a_perc_max:<tt>%3.0lf%% </tt>\l',

            'LINE1:b_perc%s:<tt>system</tt>\\t' % color_cpu_syst_line,
            'AREA:b_perc%s:' % color_cpu_syst_area,
            'GPRINT:b_perc_last:<tt>%3.0lf%% </tt>\\t',
            'GPRINT:b_perc_avg:<tt>%3.0lf%% </tt>\\t',
            'GPRINT:b_perc_max:<tt>%3.0lf%% </tt>\l',

            'LINE1:c_perc%s:<tt>nice  </tt>\\t' % color_cpu_nice_line,
            'AREA:c_perc%s:' % color_cpu_nice_area,
            'GPRINT:c_perc_last:<tt>%3.0lf%% </tt>\\t',
            'GPRINT:c_perc_avg:<tt>%3.0lf%% </tt>\\t',
            'GPRINT:c_perc_max:<tt>%3.0lf%% </tt>\l',

            'LINE1:d_perc%s:<tt>idle  </tt>\\t' % color_cpu_idle_line,
            'AREA:d_perc%s:' % color_cpu_idle_area,
            'GPRINT:d_perc_last:<tt>%3.0lf%% </tt>\\t',
            'GPRINT:d_perc_avg:<tt>%3.0lf%% </tt>\\t',
            'GPRINT:d_perc_max:<tt>%3.0lf%% </tt>\l'
            )


def plot_la(img_path, rrd_path, opt, tz=None):
    color_la1_line  = '#CF0000FF'
    color_la1_area  = '#CF000030'
    color_la5_line  = '#0000CFFF'
    color_la5_area  = '#0000CF30'
    color_la15_line = '#00CF00FF'
    color_la15_area = '#00CF0030'
    if tz:
        utc = datetime.datetime.utcnow()
        time_string = pytz.timezone(tz).fromutc(utc).strftime('%b %d, %Y %H:%M:%S %z')
    else:
        time_string = time.strftime('%b %d, %Y %H:%M:%S %z')
    rrdtool.graph(
            img_path,
            '--imgformat', 'PNG',
            '--step', opt['step'],
            '--pango-markup',
            '--vertical-label', 'Load averages',
            '--title', 'Load averages (%s)' % time_string,
            '--lower-limit', '0',
            '--alt-autoscale-max',
            '--alt-autoscale-min',
            '--rigid',
            '--no-gridfit',
            '--slope-mode',
            '--alt-y-grid',
            '--units-exponent', '0',
            '--x-grid', opt['x_grid'],
            '--end', opt['end'],
            '--start', opt['start'],
            '--width', '440',
            '--height', '140',
            '--font-render-mode', 'normal',
            '--border', '0',
            '--color', 'BACK#FFFFFF',

            'DEF:la1=%s:la1:AVERAGE' % rrd_path,
            'DEF:la5=%s:la5:AVERAGE' % rrd_path,
            'DEF:la15=%s:la15:AVERAGE' % rrd_path,
            'VDEF:la1_min=la1,MINIMUM',
            'VDEF:la1_last=la1,LAST',
            'VDEF:la1_avg=la1,AVERAGE',
            'VDEF:la1_max=la1,MAXIMUM',
            'VDEF:la5_min=la5,MINIMUM',
            'VDEF:la5_last=la5,LAST',
            'VDEF:la5_avg=la5,AVERAGE',
            'VDEF:la5_max=la5,MAXIMUM',
            'VDEF:la15_min=la15,MINIMUM',
            'VDEF:la15_last=la15,LAST',
            'VDEF:la15_avg=la15,AVERAGE',
            'VDEF:la15_max=la15,MAXIMUM',

            'COMMENT:<b><tt>                            Minimum   Current    Average   Maximum</tt></b>\l',

            'LINE1:la15%s:<tt>15 Minutes system load   </tt>' % color_la15_line,
            'AREA:la15%s:' % color_la15_area,
            'GPRINT:la15_min:<tt>%3.2lf</tt>\\t',
            'GPRINT:la15_last:<tt>%3.2lf</tt>\\t',
            'GPRINT:la15_avg:<tt>%3.2lf</tt>\\t',
            'GPRINT:la15_max:<tt>%3.2lf</tt>\l',

            'LINE1:la5%s:<tt> 5 Minutes system load   </tt>' % color_la5_line,
            'AREA:la5%s:' % color_la5_area,
            'GPRINT:la5_min:<tt>%3.2lf</tt>\\t',
            'GPRINT:la5_last:<tt>%3.2lf</tt>\\t',
            'GPRINT:la5_avg:<tt>%3.2lf</tt>\\t',
            'GPRINT:la5_max:<tt>%3.2lf</tt>\l',

            'LINE1:la1%s:<tt> 1 Minute  system load   </tt>' % color_la1_line,
            'AREA:la1%s:' % color_la1_area,
            'GPRINT:la1_min:<tt>%3.2lf</tt>\\t',
            'GPRINT:la1_last:<tt>%3.2lf</tt>\\t',
            'GPRINT:la1_avg:<tt>%3.2lf</tt>\\t',
            'GPRINT:la1_max:<tt>%3.2lf</tt>\l'
            )


def plot_mem(img_path, rrd_path, opt, tz=None):
    color_mem_shrd = '#00FFFF'
    color_mem_buff_line = '#FF0000FF'
    color_mem_buff_area = '#FF000055'
    color_mem_cach_line = '#0000FFFF'
    color_mem_cach_area = '#0000FF30'
    color_mem_free_line = '#00CF00FF'
    color_mem_free_area = '#00CF0030'
    color_mem_swap_line = '#EFEF00FF'
    color_mem_swap_area = '#EFEF0030'
    if tz:
        utc = datetime.datetime.utcnow()
        time_string = pytz.timezone(tz).fromutc(utc).strftime('%b %d, %Y %H:%M:%S %z')
    else:
        time_string = time.strftime('%b %d, %Y %H:%M:%S %z')
    rrdtool.graph(
            img_path,
            '--imgformat', 'PNG',
            '--step', opt['step'],
            '--pango-markup',
            '--vertical-label', 'Memory Usage',
            '--title', 'Memory Usage (%s)' % time_string,
            '--lower-limit', '0',
            '--base', '1024',
            '--alt-autoscale-max',
            '--alt-autoscale-min',
            '--rigid',
            '--no-gridfit',
            '--slope-mode',
            '--x-grid', opt['x_grid'],
            '--end', opt['end'],
            '--start', opt['start'],
            '--width', '440',
            '--height', '180',
            '--font-render-mode', 'normal',
            '--border', '0',
            '--color', 'BACK#FFFFFF',

            'DEF:mem1=%s:swap:AVERAGE' % rrd_path,
            'DEF:mem2=%s:swapavail:AVERAGE' % rrd_path,
            'DEF:mem3=%s:total:AVERAGE' % rrd_path,
            'DEF:mem4=%s:avail:AVERAGE' % rrd_path,
            'DEF:mem5=%s:free:AVERAGE' % rrd_path,
            'DEF:mem6=%s:shared:AVERAGE' % rrd_path,
            'DEF:mem7=%s:buffer:AVERAGE' % rrd_path,
            'DEF:mem8=%s:cached:AVERAGE' % rrd_path,
            'CDEF:swap_total=mem1,1024,*',
            'VDEF:swap_total_min=swap_total,MINIMUM',
            'VDEF:swap_total_last=swap_total,LAST',
            'VDEF:swap_total_avg=swap_total,AVERAGE',
            'VDEF:swap_total_max=swap_total,MAXIMUM',
            'CDEF:swap_avail=mem2,1024,*',
            'VDEF:swap_avail_min=swap_avail,MINIMUM',
            'VDEF:swap_avail_last=swap_avail,LAST',
            'VDEF:swap_avail_avg=swap_avail,AVERAGE',
            'VDEF:swap_avail_max=swap_avail,MAXIMUM',
            'CDEF:swap_used=swap_total,swap_avail,-',
            'VDEF:swap_used_min=swap_used,MINIMUM',
            'VDEF:swap_used_last=swap_used,LAST',
            'VDEF:swap_used_avg=swap_used,AVERAGE',
            'VDEF:swap_used_max=swap_used,MAXIMUM',
            'CDEF:mem_total=mem3,1024,*',
            'VDEF:mem_total_min=mem_total,MINIMUM',
            'VDEF:mem_total_last=mem_total,LAST',
            'VDEF:mem_total_avg=mem_total,AVERAGE',
            'VDEF:mem_total_max=mem_total,MAXIMUM',
            'CDEF:mem_avail=mem4,1024,*',
            'VDEF:mem_avail_min=mem_avail,MINIMUM',
            'VDEF:mem_avail_last=mem_avail,LAST',
            'VDEF:mem_avail_avg=mem_avail,AVERAGE',
            'VDEF:mem_avail_max=mem_avail,MAXIMUM',
            'CDEF:mem_free=mem5,1024,*',
            'VDEF:mem_free_min=mem_free,MINIMUM',
            'VDEF:mem_free_last=mem_free,LAST',
            'VDEF:mem_free_avg=mem_free,AVERAGE',
            'VDEF:mem_free_max=mem_free,MAXIMUM',
            'CDEF:mem_shared=mem6,1024,*',
            'VDEF:mem_shared_min=mem_shared,MINIMUM',
            'VDEF:mem_shared_last=mem_shared,LAST',
            'VDEF:mem_shared_avg=mem_shared,AVERAGE',
            'VDEF:mem_shared_max=mem_shared,MAXIMUM',
            'CDEF:mem_buffer=mem7,1024,*',
            'VDEF:mem_buffer_min=mem_buffer,MINIMUM',
            'VDEF:mem_buffer_last=mem_buffer,LAST',
            'VDEF:mem_buffer_avg=mem_buffer,AVERAGE',
            'VDEF:mem_buffer_max=mem_buffer,MAXIMUM',
            'CDEF:mem_cached=mem8,1024,*',
            'VDEF:mem_cached_min=mem_cached,MINIMUM',
            'VDEF:mem_cached_last=mem_cached,LAST',
            'VDEF:mem_cached_avg=mem_cached,AVERAGE',
            'VDEF:mem_cached_max=mem_cached,MAXIMUM',

            'COMMENT:<b><tt>                  Minimum   Current    Average   Maximum</tt></b>\l',

            'LINE1:mem_shared%s:<tt>Shared        </tt>' % color_mem_shrd,
            'GPRINT:swap_total_min:<tt>%4.1lf%s</tt>\\t',
            'GPRINT:swap_total_last:<tt>%4.1lf%s</tt>\\t',
            'GPRINT:swap_total_avg:<tt>%4.1lf%s</tt>\\t',
            'GPRINT:swap_total_max:<tt>%4.1lf%s</tt>\l',

            'LINE1:mem_buffer%s:<tt>Buffer        </tt>' % color_mem_buff_line,
            'AREA:mem_buffer%s:' % color_mem_buff_area,
            'GPRINT:mem_buffer_min:<tt>%4.1lf%s</tt>\\t',
            'GPRINT:mem_buffer_last:<tt>%4.1lf%s</tt>\\t',
            'GPRINT:mem_buffer_avg:<tt>%4.1lf%s</tt>\\t',
            'GPRINT:mem_buffer_max:<tt>%4.1lf%s</tt>\l',

            'LINE1:mem_cached%s:<tt>Cached        </tt>' % color_mem_cach_line,
            'AREA:mem_cached%s:' % color_mem_cach_area,
            'GPRINT:mem_cached_min:<tt>%4.1lf%s</tt>\\t',
            'GPRINT:mem_cached_last:<tt>%4.1lf%s</tt>\\t',
            'GPRINT:mem_cached_avg:<tt>%4.1lf%s</tt>\\t',
            'GPRINT:mem_cached_max:<tt>%4.1lf%s</tt>\l',

            'LINE1:mem_free%s:<tt>Free          </tt>' % color_mem_free_line,
            'AREA:mem_free%s:' % color_mem_free_area,
            'GPRINT:mem_free_min:<tt>%4.1lf%s</tt>\\t',
            'GPRINT:mem_free_last:<tt>%4.1lf%s</tt>\\t',
            'GPRINT:mem_free_avg:<tt>%4.1lf%s</tt>\\t',
            'GPRINT:mem_free_max:<tt>%4.1lf%s</tt>\l',

            'LINE1:swap_used%s:<tt>Swap In Use   </tt>' % color_mem_swap_line,
            'AREA:swap_used%s:' % color_mem_swap_area,
            'GPRINT:swap_used_min:<tt>%4.1lf%s</tt>\\t',
            'GPRINT:swap_used_last:<tt>%4.1lf%s</tt>\\t',
            'GPRINT:swap_used_avg:<tt>%4.1lf%s</tt>\\t',
            'GPRINT:swap_used_max:<tt>%4.1lf%s</tt>\l'
            )


def plot_net(img_path, rrd_path, opt, tz=None):
    color_ibound_line = '#CF0000FF'
    color_ibound_area = '#CF000030'
    color_obound_line = '#0000CFFF'
    color_obound_area = '#0000CF30'
    if tz:
        utc = datetime.datetime.utcnow()
        time_string = pytz.timezone(tz).fromutc(utc).strftime('%b %d, %Y %H:%M:%S %z')
    else:
        time_string = time.strftime('%b %d, %Y %H:%M:%S %z')
    rrdtool.graph(
            img_path,
            '--imgformat', 'PNG',
            '--step', opt['step'],
            '--pango-markup',
            '--vertical-label', 'Bits per second',
            '--title', 'Network usage (%s)' % time_string,
            '--lower-limit', '0',
            '--alt-autoscale-max',
            '--alt-autoscale-min',
            '--rigid',
            '--no-gridfit',
            '--slope-mode',
            '--x-grid', opt['x_grid'],
            '--end', opt['end'],
            '--start', opt['start'],
            '--width', '440',
            '--height', '100',
            '--font-render-mode', 'normal',
            '--border', '0',
            '--color', 'BACK#FFFFFF',

            'DEF:in=%s:in:AVERAGE' % rrd_path,
            'DEF:out=%s:out:AVERAGE' % rrd_path,
            'CDEF:in_bits=in,8,*',
            'CDEF:out_bits=out,8,*',
            'VDEF:in_last=in_bits,LAST',
            'VDEF:in_avg=in_bits,AVERAGE',
            'VDEF:in_max=in_bits,MAXIMUM',
            'VDEF:out_last=out_bits,LAST',
            'VDEF:out_avg=out_bits,AVERAGE',
            'VDEF:out_max=out_bits,MAXIMUM',

            'COMMENT:<b><tt>          Current   Average   Maximum</tt></b>\\l',

            'LINE1:in_bits%s:<tt>In    </tt>' % color_ibound_line,
            'AREA:in_bits%s:' % color_ibound_area,
            'GPRINT:in_last:<tt>%4.1lf%s</tt>\\t',
            'GPRINT:in_avg:<tt>%4.1lf%s</tt>\\t',
            'GPRINT:in_max:<tt>%4.1lf%s</tt>\l',

            'LINE1:out_bits%s:<tt>Out   </tt>' % color_obound_line,
            'AREA:out_bits%s:' % color_obound_area,
            'GPRINT:out_last:<tt>%4.1lf%s</tt>\\t',
            'GPRINT:out_avg:<tt>%4.1lf%s</tt>\\t',
            'GPRINT:out_max:<tt>%4.1lf%s</tt>\l'
            )


def plot_snum(img_path, rrd_path, opt, tz=None):
    color_running_servers_line = '#00CF00FF'
    color_running_servers_area = '#00CF0044'
    if tz:
        utc = datetime.datetime.utcnow()
        time_string = pytz.timezone(tz).fromutc(utc).strftime('%b %d, %Y %H:%M:%S %z')
    else:
        time_string = time.strftime('%b %d, %Y %H:%M:%S %z')
    rrdtool.graph(
            img_path,
            '--imgformat', 'PNG',
            '--step', opt['step'],
            '--pango-markup',
            '--vertical-label', 'Servers',
            '--title', 'Servers count (%s)' % time_string,
            '--alt-autoscale-max',
            '--alt-autoscale-min',
            '--lower-limit', '0',
            '--y-grid', '1:1',
            '--units-exponent', '0',
            '--rigid',
            '--no-gridfit',
            '--slope-mode',
            '--x-grid', opt['x_grid'],
            '--end', opt['end'],
            '--start', opt['start'],
            '--width', '440',
            '--height', '100',
            '--font-render-mode', 'normal',
            '--border', '0',
            '--color', 'BACK#FFFFFF',

            'DEF:s_running=%s:s_running:AVERAGE' % rrd_path,
            'VDEF:s_running_last=s_running,LAST',
            'VDEF:s_running_avg=s_running,AVERAGE',
            'VDEF:s_running_max=s_running,MAXIMUM',
            'VDEF:s_running_min=s_running,MINIMUM',

            'COMMENT:<b><tt>                      Current    Average   Maximum   Minimum</tt></b>\l',

            'LINE1:s_running%s:<tt>Running servers    </tt>' % color_running_servers_line,
            'AREA:s_running%s:' % color_running_servers_area,
            'GPRINT:s_running_last:<tt>%3.0lf</tt>\\t',
            'GPRINT:s_running_avg:<tt>%3.0lf</tt>\\t',
            'GPRINT:s_running_max:<tt>%3.0lf</tt>\\t',
            'GPRINT:s_running_min:<tt>%3.0lf</tt>\l'
            )


def plot_io_bits(img_path, rrd_path, opt, tz=None):
    color_r = '#ff0000'
    color_w = '#0000ff'
    if tz:
        utc = datetime.datetime.utcnow()
        time_string = pytz.timezone(tz).fromutc(utc).strftime('%b %d, %Y %H:%M:%S %z')
    else:
        time_string = time.strftime('%b %d, %Y %H:%M:%S %z')
    rrdtool.graph(
            img_path,
            '--imgformat', 'PNG',
            '--step', opt['step'],
            '--pango-markup',
            '--vertical-label', 'Bits per second',
            '--title', 'Disk I/O (%s)' % time_string,
            '--lower-limit', '0',
            '--alt-autoscale-max',
            '--alt-autoscale-min',
            '--rigid',
            '--no-gridfit',
            '--slope-mode',
            '--x-grid', opt['x_grid'],
            '--end', opt['end'],
            '--start', opt['start'],
            '--width', '440',
            '--height', '100',
            '--font-render-mode', 'normal',
            '--border', '0',
            '--color', 'BACK#FFFFFF',

            'DEF:rbyte=%s:rbyte:AVERAGE' % rrd_path,
            'DEF:wbyte=%s:wbyte:AVERAGE' % rrd_path,
            'CDEF:rbits=rbyte,8,*',
            'CDEF:wbits=wbyte,8,*',
            'VDEF:rbits_last=rbits,LAST',
            'VDEF:rbits_avg=rbits,AVERAGE',
            'VDEF:rbits_max=rbits,MAXIMUM',
            'VDEF:wbits_last=wbits,LAST',
            'VDEF:wbits_avg=wbits,AVERAGE',
            'VDEF:wbits_max=wbits,MAXIMUM',

            'COMMENT:<b><tt>                Current    Average   Maximum</tt></b>\\l',

            'AREA:wbits%s:<tt>Write bits   </tt>' % color_w,
            'GPRINT:wbits_last:<tt>%4.1lf%s</tt>\\t',
            'GPRINT:wbits_avg:<tt>%4.1lf%s</tt>\\t',
            'GPRINT:wbits_max:<tt>%4.1lf%s</tt>\l',

            'AREA:rbits%s:<tt>Read  bits   </tt>' % color_r,
            'GPRINT:rbits_last:<tt>%4.1lf%s</tt>\\t',
            'GPRINT:rbits_avg:<tt>%4.1lf%s</tt>\\t',
            'GPRINT:rbits_max:<tt>%4.1lf%s</tt>\l'
            )


def plot_io_ops(img_path, rrd_path, opt, tz=None):
    color_r = '#ff0000'
    color_w = '#0000ff'
    if tz:
        utc = datetime.datetime.utcnow()
        time_string = pytz.timezone(tz).fromutc(utc).strftime('%b %d, %Y %H:%M:%S %z')
    else:
        time_string = time.strftime('%b %d, %Y %H:%M:%S %z')
    rrdtool.graph(
            img_path,
            '--imgformat', 'PNG',
            '--step', opt['step'],
            '--pango-markup',
            '--vertical-label', 'Operations per second',
            '--title', 'Disk I/O (%s)' % time_string,
            '--lower-limit', '0',
            '--alt-autoscale-max',
            '--alt-autoscale-min',
            '--rigid',
            '--no-gridfit',
            '--slope-mode',
            '--x-grid', opt['x_grid'],
            '--end', opt['end'],
            '--start', opt['start'],
            '--width', '440',
            '--height', '100',
            '--font-render-mode', 'normal',
            '--border', '0',
            '--color', 'BACK#FFFFFF',

            'DEF:read=%s:read:AVERAGE' % rrd_path,
            'DEF:write=%s:write:AVERAGE' % rrd_path,
            'VDEF:read_last=read,LAST',
            'VDEF:read_avg=read,AVERAGE',
            'VDEF:read_max=read,MAXIMUM',
            'VDEF:write_last=write,LAST',
            'VDEF:write_avg=write,AVERAGE',
            'VDEF:write_max=write,MAXIMUM',

            'COMMENT:<b><tt>             Current    Average   Maximum</tt></b>\\l',

            'AREA:write%s:<tt>Writes     </tt>' % color_w,
            'GPRINT:write_last:<tt>%4.1lf%s</tt>\\t',
            'GPRINT:write_avg:<tt>%4.1lf%s</tt>\\t',
            'GPRINT:write_max:<tt>%4.1lf%s</tt>\l',

            'AREA:read%s:<tt>Reads     </tt>' % color_r,
            'GPRINT:read_last:<tt>%4.1lf%s</tt>\\t',
            'GPRINT:read_avg:<tt>%4.1lf%s</tt>\\t',
            'GPRINT:read_max:<tt>%4.1lf%s</tt>\l'
            )

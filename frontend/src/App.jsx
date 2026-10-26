import { useCallback, useEffect, useState } from 'react';
import './App.css';

const API_URL = 'http://localhost/inventario-sd/backend/public/api';

const valoresIniciales = {
  numero_serie: '',
  tipo_equipo: 'Laptop',
  marca: '',
  modelo: '',
  clasificacion: 'Stock',
  cantidad: 1,
  estado_equipo: 'Nuevo',
  observaciones: '',
};

function App() {
  const [usuario, setUsuario] = useState('');
  const [contrasena, setContrasena] = useState('');
  const [mostrarContrasena, setMostrarContrasena] = useState(false);
  const [cargando, setCargando] = useState(false);
  const [sesionCargando, setSesionCargando] = useState(true);
  const [mensaje, setMensaje] = useState('');
  const [tipoMensaje, setTipoMensaje] = useState('error');
  const [usuarioActual, setUsuarioActual] = useState(null);
  const [csrf, setCsrf] = useState('');
  const [productos, setProductos] = useState([]);
  const [estadisticas, setEstadisticas] = useState({});
  const [busqueda, setBusqueda] = useState('');
  const [cargandoProductos, setCargandoProductos] = useState(false);
  const [modalAbierto, setModalAbierto] = useState(false);
  const [guardandoProducto, setGuardandoProducto] = useState(false);
  const [formulario, setFormulario] = useState(valoresIniciales);
  const [vista, setVista] = useState('inventario');

  const mostrarAviso = (texto, tipo = 'error') => {
    setMensaje(texto);
    setTipoMensaje(tipo);
  };

  const cargarProductos = useCallback(async (termino = '') => {
    setCargandoProductos(true);

    try {
      const url = new URL(`${API_URL}/productos`);
      if (termino.trim()) url.searchParams.set('q', termino.trim());

      const respuesta = await fetch(url, {
        credentials: 'include',
        headers: { Accept: 'application/json' },
      });
      const resultado = await respuesta.json();

      if (respuesta.status === 401) {
        setUsuarioActual(null);
        sessionStorage.removeItem('inventario_sd_usuario');
        throw new Error('Tu sesión terminó. Inicia sesión nuevamente.');
      }

      if (!respuesta.ok || resultado.success !== true) {
        throw new Error(
          resultado.error?.message || 'No se pudo cargar el inventario.'
        );
      }

      setProductos(resultado.data?.productos ?? []);
      setEstadisticas(resultado.data?.estadisticas ?? {});
    } catch (error) {
      mostrarAviso(error.message || 'Error al consultar los productos.');
    } finally {
      setCargandoProductos(false);
    }
  }, []);

  const recuperarSesion = useCallback(async () => {
    try {
      const respuesta = await fetch(`${API_URL}/auth/me`, {
        credentials: 'include',
        headers: { Accept: 'application/json' },
      });

      if (!respuesta.ok) {
        setUsuarioActual(null);
        return;
      }

      const resultado = await respuesta.json();
      const user = resultado.data?.user;
      const token = resultado.data?.csrf_token;

      if (resultado.success && user) {
        setUsuarioActual(user);
        setCsrf(token || '');
        sessionStorage.setItem('inventario_sd_usuario', JSON.stringify(user));
        if (token) sessionStorage.setItem('inventario_sd_csrf', token);
      }
    } catch {
      // Si el servidor no está disponible, se conserva visible el acceso.
    } finally {
      setSesionCargando(false);
    }
  }, []);

  useEffect(() => {
    recuperarSesion();
  }, [recuperarSesion]);

  useEffect(() => {
    if (usuarioActual) cargarProductos(busqueda);
  }, [usuarioActual, busqueda, cargarProductos]);

  const manejarInicioSesion = async (e) => {
    e.preventDefault();
    if (cargando) return;

    setCargando(true);
    mostrarAviso('');

    try {
      const respuesta = await fetch(`${API_URL}/auth/login`, {
        method: 'POST',
        credentials: 'include',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json',
        },
        body: JSON.stringify({
          username: usuario.trim(),
          password: contrasena,
        }),
      });

      const resultado = await respuesta.json();

      if (!respuesta.ok || resultado.success !== true) {
        throw new Error(
          resultado.error?.message ||
          resultado.message ||
          'No se pudo iniciar sesión. Verifica tus datos.'
        );
      }

      let user = resultado.data?.user;
      let token = resultado.data?.csrf_token || resultado.csrf_token;

      // Algunas respuestas de autenticación pueden entregar el token en /me.
      if (!user || !token) {
        const respuestaSesion = await fetch(`${API_URL}/auth/me`, {
          credentials: 'include',
          headers: { Accept: 'application/json' },
        });
        const sesion = await respuestaSesion.json();

        if (respuestaSesion.ok && sesion.success) {
          user = user || sesion.data?.user;
          token = token || sesion.data?.csrf_token;
        }
      }

      if (!user) {
        throw new Error('El servidor no devolvió los datos del usuario.');
      }

      setUsuarioActual(user);
      setCsrf(token || '');
      sessionStorage.setItem('inventario_sd_usuario', JSON.stringify(user));
      if (token) sessionStorage.setItem('inventario_sd_csrf', token);

      setContrasena('');
      mostrarAviso(`Bienvenido, ${user.nombre_completo}.`, 'exito');
      await cargarProductos('');
      setBusqueda('');
    } catch (error) {
      mostrarAviso(
        error instanceof TypeError
          ? 'No se pudo conectar con el servidor. Verifica Apache y la API.'
          : error.message || 'Ocurrió un error al iniciar sesión.'
      );
    } finally {
      setCargando(false);
    }
  };

  const cerrarSesion = async () => {
    try {
      let token = csrf || sessionStorage.getItem('inventario_sd_csrf') || '';

      if (!token) {
        const sesionRespuesta = await fetch(`${API_URL}/auth/me`, {
          credentials: 'include',
        });
        const sesion = await sesionRespuesta.json();
        token = sesion.data?.csrf_token || '';
      }

      const respuesta = await fetch(`${API_URL}/auth/logout`, {
        method: 'POST',
        credentials: 'include',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-Token': token,
          Accept: 'application/json',
        },
        body: JSON.stringify({}),
      });

      if (!respuesta.ok) {
        const resultado = await respuesta.json().catch(() => ({}));
        throw new Error(
          resultado.error?.message || 'No se pudo cerrar la sesión en el servidor.'
        );
      }
    } catch (error) {
      mostrarAviso(error.message || 'No se pudo cerrar la sesión.');
      return;
    }

    setUsuarioActual(null);
    setProductos([]);
    setEstadisticas({});
    setCsrf('');
    sessionStorage.removeItem('inventario_sd_usuario');
    sessionStorage.removeItem('inventario_sd_csrf');
    setVista('inventario');
    mostrarAviso('Sesión cerrada correctamente.', 'exito');
  };

  const cambiarCampo = (e) => {
    const { name, value } = e.target;
    setFormulario((actual) => ({ ...actual, [name]: value }));
  };

  const registrarProducto = async (e) => {
    e.preventDefault();
    if (guardandoProducto) return;

    setGuardandoProducto(true);
    mostrarAviso('');

    try {
      let token = csrf || sessionStorage.getItem('inventario_sd_csrf') || '';

      if (!token) {
        const sesionRespuesta = await fetch(`${API_URL}/auth/me`, {
          credentials: 'include',
        });
        const sesion = await sesionRespuesta.json();
        token = sesion.data?.csrf_token || '';
        if (token) {
          setCsrf(token);
          sessionStorage.setItem('inventario_sd_csrf', token);
        }
      }

      if (!token) {
        throw new Error('No se obtuvo el token de seguridad. Cierra sesión e ingresa nuevamente.');
      }

      const respuesta = await fetch(`${API_URL}/productos`, {
        method: 'POST',
        credentials: 'include',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-Token': token,
          Accept: 'application/json',
        },
        body: JSON.stringify({
          ...formulario,
          cantidad: Number(formulario.cantidad),
        }),
      });

      const resultado = await respuesta.json();

      if (!respuesta.ok || resultado.success !== true) {
        throw new Error(
          resultado.error?.message ||
          resultado.message ||
          'No se pudo registrar el producto.'
        );
      }

      setFormulario(valoresIniciales);
      setModalAbierto(false);
      setBusqueda('');
      mostrarAviso('Producto registrado correctamente.', 'exito');
      await cargarProductos('');
    } catch (error) {
      mostrarAviso(error.message || 'Ocurrió un error al registrar el producto.');
    } finally {
      setGuardandoProducto(false);
    }
  };

  if (sesionCargando) {
    return (
      <main className="loading-page">
        <div className="loading-logo">SD</div>
        <p>Comprobando sesión...</p>
      </main>
    );
  }

  if (!usuarioActual) {
    return (
      <main className="login-page">
        <section className="login-container">
          <div className="login-brand">
            <div className="brand-icon">SD</div>
            <h1>INVENTARIO SD</h1>
            <p>Control y administración de inventario</p>
          </div>

          <div className="login-form-container">
            <h2>Bienvenido</h2>
            <p className="login-description">Ingresa tus datos para continuar.</p>

            {mensaje && (
              <div className={`alert ${tipoMensaje === 'exito' ? 'alert-success' : 'alert-danger'}`}
                role="status" aria-live="polite">
                {mensaje}
              </div>
            )}

            <form onSubmit={manejarInicioSesion}>
              <div className="mb-3">
                <label htmlFor="usuario" className="form-label">Nombre de usuario</label>
                <input
                  id="usuario"
                  type="text"
                  className="form-control"
                  placeholder="Escribe tu usuario"
                  value={usuario}
                  onChange={(e) => setUsuario(e.target.value)}
                  autoComplete="username"
                  required
                  disabled={cargando}
                />
              </div>

              <div className="mb-4">
                <label htmlFor="contrasena" className="form-label">Contraseña</label>
                <div className="password-container">
                  <input
                    id="contrasena"
                    type={mostrarContrasena ? 'text' : 'password'}
                    className="form-control"
                    placeholder="Escribe tu contraseña"
                    value={contrasena}
                    onChange={(e) => setContrasena(e.target.value)}
                    autoComplete="current-password"
                    required
                    disabled={cargando}
                  />
                  <button
                    type="button"
                    className="password-toggle"
                    onClick={() => setMostrarContrasena(!mostrarContrasena)}
                    aria-label={mostrarContrasena ? 'Ocultar contraseña' : 'Mostrar contraseña'}
                    disabled={cargando}
                  >
                    {mostrarContrasena ? 'Ocultar' : 'Mostrar'}
                  </button>
                </div>
              </div>

              <button type="submit" className="login-button" disabled={cargando}>
                {cargando ? 'Verificando...' : 'Iniciar sesión'}
              </button>
            </form>

            <p className="login-footer">Sistema de gestión de inventario</p>
          </div>
        </section>
        <footer className="page-footer">INVENTARIO SD © 2026</footer>
      </main>
    );
  }

  return (
    <main className="dashboard-page">
      <aside className="sidebar">
        <div className="sidebar-brand">
          <div className="sidebar-logo">SD</div>
          <div>
            <strong>INVENTARIO SD</strong>
            <span>Gestión de equipos</span>
          </div>
        </div>

        <div className="sidebar-section-label">MENÚ PRINCIPAL</div>
        <button className={`nav-item ${vista === 'inventario' ? 'active' : ''}`}
          onClick={() => setVista('inventario')}>
          <span className="nav-icon">▦</span> Inventario
        </button>
        <button className={`nav-item ${vista === 'resumen' ? 'active' : ''}`}
          onClick={() => setVista('resumen')}>
          <span className="nav-icon">▤</span> Resumen
        </button>

        <div className="sidebar-bottom">
          <div className="sidebar-user-avatar">
            {(usuarioActual.nombre_completo || 'U').slice(0, 1).toUpperCase()}
          </div>
          <div className="sidebar-user-info">
            <strong>{usuarioActual.nombre_completo}</strong>
            <span>{usuarioActual.rol === 'admin' ? 'Administrador' : 'Usuario'}</span>
          </div>
          <button className="logout-icon" onClick={cerrarSesion} title="Cerrar sesión"
            aria-label="Cerrar sesión">↪</button>
        </div>
      </aside>

      <section className="dashboard-main">
        <header className="topbar">
          <div>
            <span className="breadcrumb">Inicio /</span>
            <strong>{vista === 'inventario' ? ' Inventario' : ' Resumen'}</strong>
          </div>
          <div className="topbar-right">
            <span className="connection-indicator"><i /> Sistema conectado</span>
            <div className="topbar-avatar">
              {(usuarioActual.nombre_completo || 'U').slice(0, 1).toUpperCase()}
            </div>
          </div>
        </header>

        <div className="dashboard-content">
          <div className="welcome-row">
            <div>
              <p className="eyebrow">PANEL DE CONTROL</p>
              <h1>{vista === 'inventario' ? 'Inventario de equipos' : 'Resumen general'}</h1>
              <p className="page-description">
                Administra y consulta los equipos registrados en el sistema.
              </p>
            </div>
            {vista === 'inventario' && (
              <button className="primary-button" onClick={() => {
                setFormulario(valoresIniciales);
                setModalAbierto(true);
              }}>
                <span>＋</span> Registrar producto
              </button>
            )}
          </div>

          {mensaje && (
            <div className={`notice ${tipoMensaje === 'exito' ? 'notice-success' : 'notice-error'}`}
              role="status" aria-live="polite">
              <span>{tipoMensaje === 'exito' ? '✓' : '!'}</span>
              {mensaje}
              <button onClick={() => setMensaje('')} aria-label="Cerrar aviso">×</button>
            </div>
          )}

          <div className="stats-grid">
            <article className="stat-card">
              <div className="stat-top"><span>Total de productos</span><span className="stat-icon blue">▦</span></div>
              <strong>{estadisticas.total_productos ?? 0}</strong>
              <small>Registros en el inventario</small>
            </article>
            <article className="stat-card">
              <div className="stat-top"><span>Unidades registradas</span><span className="stat-icon violet">▤</span></div>
              <strong>{estadisticas.total_unidades ?? 0}</strong>
              <small>Unidades contabilizadas</small>
            </article>
            <article className="stat-card">
              <div className="stat-top"><span>Equipos en stock</span><span className="stat-icon green">✓</span></div>
              <strong>{estadisticas.en_stock ?? 0}</strong>
              <small>Productos clasificados en stock</small>
            </article>
            <article className="stat-card">
              <div className="stat-top"><span>Pendientes</span><span className="stat-icon orange">◷</span></div>
              <strong>{estadisticas.pendientes ?? 0}</strong>
              <small>Productos por revisar</small>
            </article>
          </div>

          {vista === 'resumen' ? (
            <section className="content-card summary-card">
              <div className="card-heading">
                <div><h2>Distribución del inventario</h2><p>Productos agrupados por clasificación</p></div>
              </div>
              {[
                ['Stock', estadisticas.en_stock ?? 0, 'green-bar'],
                ['Pendiente', estadisticas.pendientes ?? 0, 'orange-bar'],
                ['Repuesto', estadisticas.repuestos ?? 0, 'blue-bar'],
                ['Basura', estadisticas.basura ?? 0, 'gray-bar'],
              ].map(([nombre, total, clase]) => {
                const suma = Number(estadisticas.total_productos || 0);
                const porcentaje = suma > 0 ? Math.min(100, (Number(total) / suma) * 100) : 0;
                return (
                  <div className="distribution-row" key={nombre}>
                    <div className="distribution-label"><span>{nombre}</span><strong>{total}</strong></div>
                    <div className="progress-track"><div className={clase} style={{ width: `${porcentaje}%` }} /></div>
                  </div>
                );
              })}
            </section>
          ) : (
            <section className="content-card">
              <div className="card-heading">
                <div>
                  <h2>Productos registrados</h2>
                  <p>Consulta y busca los equipos guardados en la base de datos.</p>
                </div>
                <button className="secondary-button" onClick={() => cargarProductos(busqueda)} disabled={cargandoProductos}>
                  ↻ Actualizar
                </button>
              </div>

              <div className="table-toolbar">
                <div className="search-box">
                  <span>⌕</span>
                  <input
                    type="search"
                    placeholder="Buscar por serie, tipo, marca o modelo..."
                    value={busqueda}
                    onChange={(e) => setBusqueda(e.target.value)}
                  />
                  {busqueda && <button onClick={() => setBusqueda('')} aria-label="Limpiar búsqueda">×</button>}
                </div>
                <span className="result-count">{productos.length} resultado(s)</span>
              </div>

              <div className="table-wrap">
                <table className="inventory-table">
                  <thead>
                    <tr>
                      <th>NÚMERO DE SERIE</th>
                      <th>TIPO DE EQUIPO</th>
                      <th>MARCA / MODELO</th>
                      <th>CLASIFICACIÓN</th>
                      <th>CANTIDAD</th>
                      <th>ESTADO</th>
                    </tr>
                  </thead>
                  <tbody>
                    {cargandoProductos ? (
                      <tr><td colSpan="6" className="empty-cell">Cargando productos...</td></tr>
                    ) : productos.length === 0 ? (
                      <tr>
                        <td colSpan="6" className="empty-cell">
                          <div className="empty-state-icon">▦</div>
                          <strong>No hay productos para mostrar</strong>
                          <span>{busqueda ? 'Prueba con otro término de búsqueda.' : 'Registra tu primer equipo para comenzar.'}</span>
                        </td>
                      </tr>
                    ) : productos.map((producto) => (
                      <tr key={producto.id}>
                        <td><span className="serial-value">{producto.numero_serie}</span></td>
                        <td>{producto.tipo_equipo}</td>
                        <td><strong className="table-brand">{producto.marca}</strong><span className="table-model">{producto.modelo}</span></td>
                        <td><span className={`status-pill status-${String(producto.clasificacion).toLowerCase()}`}>{producto.clasificacion}</span></td>
                        <td><span className="quantity-value">{producto.cantidad}</span></td>
                        <td><span className={`equipment-state ${producto.estado_equipo === 'Nuevo' ? 'state-new' : 'state-used'}`}>{producto.estado_equipo}</span></td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
              <div className="table-footer">
                <span>Se muestran hasta 500 registros por consulta.</span>
                <span>Datos almacenados en MySQL</span>
              </div>
            </section>
          )}

          <footer className="dashboard-footer">
            <span>INVENTARIO SD © 2026</span>
            <span>Sistema de gestión de inventario</span>
          </footer>
        </div>
      </section>

      {modalAbierto && (
        <div className="modal-backdrop" onMouseDown={(e) => {
          if (e.target === e.currentTarget && !guardandoProducto) setModalAbierto(false);
        }}>
          <section className="product-modal" role="dialog" aria-modal="true" aria-labelledby="modal-title">
            <div className="modal-header">
              <div><p className="eyebrow">INVENTARIO SD</p><h2 id="modal-title">Registrar producto</h2><p>Completa los datos del equipo que deseas agregar.</p></div>
              <button className="modal-close" onClick={() => setModalAbierto(false)} disabled={guardandoProducto} aria-label="Cerrar">×</button>
            </div>

            <form onSubmit={registrarProducto}>
              <div className="modal-form-grid">
                <div className="field-group field-full">
                  <label htmlFor="numero_serie">Número de serie <span>*</span></label>
                  <input id="numero_serie" name="numero_serie" value={formulario.numero_serie} onChange={cambiarCampo} maxLength="100" required placeholder="Ej. SN-2026-001" />
                  <small>Debe ser único para cada registro.</small>
                </div>
                <div className="field-group">
                  <label htmlFor="tipo_equipo">Tipo de equipo <span>*</span></label>
                  <select id="tipo_equipo" name="tipo_equipo" value={formulario.tipo_equipo} onChange={cambiarCampo} required>
                    {['Laptop', 'All-In-One', 'Tablet', 'Teléfono', 'Antena', 'Monitor', 'Impresora', 'Disco Duro', 'Memoria RAM', 'Otros'].map(tipo => <option key={tipo}>{tipo}</option>)}
                  </select>
                </div>
                <div className="field-group">
                  <label htmlFor="marca">Marca <span>*</span></label>
                  <input id="marca" name="marca" value={formulario.marca} onChange={cambiarCampo} maxLength="100" required placeholder="Ej. Lenovo" />
                </div>
                <div className="field-group">
                  <label htmlFor="modelo">Modelo <span>*</span></label>
                  <input id="modelo" name="modelo" value={formulario.modelo} onChange={cambiarCampo} maxLength="150" required placeholder="Ej. ThinkPad T14" />
                </div>
                <div className="field-group">
                  <label htmlFor="clasificacion">Clasificación</label>
                  <select id="clasificacion" name="clasificacion" value={formulario.clasificacion} onChange={cambiarCampo}>
                    {['Stock', 'Pendiente', 'Repuesto', 'Basura'].map(item => <option key={item}>{item}</option>)}
                  </select>
                </div>
                <div className="field-group">
                  <label htmlFor="cantidad">Cantidad</label>
                  <input id="cantidad" name="cantidad" type="number" min="1" max="100000" step="1" value={formulario.cantidad} onChange={cambiarCampo} required />
                </div>
                <div className="field-group">
                  <label htmlFor="estado_equipo">Estado del equipo</label>
                  <select id="estado_equipo" name="estado_equipo" value={formulario.estado_equipo} onChange={cambiarCampo}>
                    <option>Nuevo</option><option>Usado</option>
                  </select>
                </div>
                <div className="field-group field-full">
                  <label htmlFor="observaciones">Observaciones</label>
                  <textarea id="observaciones" name="observaciones" value={formulario.observaciones} onChange={cambiarCampo} maxLength="5000" rows="3" placeholder="Información adicional del equipo (opcional)" />
                </div>
              </div>

              <div className="modal-actions">
                <button type="button" className="secondary-button" onClick={() => setModalAbierto(false)} disabled={guardandoProducto}>Cancelar</button>
                <button type="submit" className="primary-button" disabled={guardandoProducto}>
                  {guardandoProducto ? 'Guardando...' : 'Guardar producto'}
                </button>
              </div>
            </form>
          </section>
        </div>
      )}
    </main>
  );
}

export default App;
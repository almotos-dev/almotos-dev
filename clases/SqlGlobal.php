<?php

/**
 *
 * @package     FOM
 * @subpackage  Base
 * @author      Pablo Andr�s V�lez Vidal
 * @license     http://www.gnu.org/licenses/gpl.txt
 * @copyright   Copyright (c) 2015 Almotos
 * @version     0.1
 *
 **/

class SqlGlobal {



    /**
     * Nombre o direcci�n IP del servidor de bases de datos MySQL
     * @var cadena
     */
    public $servidor;



    /**
     * Nombre de usuario para la conexi�n al servidor de bases de datos MySQL
     * @var cadena
     */
    public $usuario;



    /**
     * Contrase�a del usuario para la conexi�n al servidor de bases de datos MySQL
     * @var cadena
     */
    public $contrasena;



    /**
     * Nombre de la base datos para la conexi�n al servidor de bases de datos MySQL
     * @var cadena
     */
    public $baseDatos;



    /**
     * Prefijo para las tablas y vistas del proyecto en la base de datos MySQL
     * @var cadena
     */
    public $prefijo;



    /**
     * Gestor de la conexi�n a la base de datos MySQL
     * @var recurso
     */
    public $conexion;

    
    /**
     * Objeto resultado devuelto al ejecutar un query
     * @var recurso
     */
    public $resultado;    
    
    /**
     * Objeto resultado devuelto al ejecutar un query
     * @var recurso
     */
    public $prefijoDepuracion = '';       


    /**
     * N�mero asignado para el �ltimo registro adicionado mediante incremento autom�tico
     * @var entero
     */
    public $ultimoId;



    /**
     * N�mero de filas devueltas por una consulta
     * @var entero
     */
    public $filasDevueltas;


    /**
     * N�mero de filas afectadas por una consulta
     * @var recurso
     */
    public $filasAfectadas;



    /**
     * N�mero de consultas realizadas en cada p�gina generada
     * @var entero
     */
    public $consultas;



    /**
     * Tiempo total empleado para las consultas realizadas (en segundos)
     * @var flotante
     */
    public $tiempo;

    
    /**
     * Tiempo total empleado para las consultas realizadas (en segundos)
     * @var flotante
     */
    public $sentenciaSql;


    /**
     * Depurar las consultas realizadas en la base de datos MySQL mediante los archivos de registro (logs)
     * @var l�gico
     */
    public $depurar = false;
    
    /**
     * Determina si algunos parametros de la sentencia deben ser guardados en la bitacora
     * @var l�gico
     */
    public $guardarBitacora = true;    





    /**
     *
     * Inicializar la clase estableciendo una conexi�n con el servidor de bases de datos MySQL
     *
     * @param cadena $servidor      Nombre o direcci�n IP del servidor de bases de datos MySQL
     * @param cadena $usuario       Nombre de usuario para la conexi�n al servidor de bases de datos MySQL
     * @param cadena $contrasena    Contrase�a del usuario para la conexi�n al servidor de bases de datos MySQL
     * @param cadena $nombre        Nombre de la base datos para la conexi�n al servidor de bases de datos MySQL
     * @return                      recurso
     *
     */
    function __construct($servidor = '', $usuario = '', $contrasena = '', $nombre = '') {
        global $configuracion;

        if (empty($servidor) && empty($usuario) && empty($usuario) && empty($usuario)) {
            $this->servidor   = $configuracion['BASEDATOS_GLOBAL']['servidor'];
            $this->usuario    = $configuracion['BASEDATOS_GLOBAL']['usuario'];
            $this->contrasena = $configuracion['BASEDATOS_GLOBAL']['contrase�a'];
            $this->baseDatos  = $configuracion['BASEDATOS_GLOBAL']['nombre'];
            $this->prefijo    = $configuracion['BASEDATOS_GLOBAL']['prefijo'];

        } else {
            $this->servidor   = $servidor;
            $this->usuario    = $usuario;
            $this->contrasena = $contrasena;
            $this->baseDatos  = $nombre;
            $this->prefijo    = '';
        }

        $this->conectar();
    }





    /**
     *
     * Establecer una conexi�n con el servidor de bases de datos MySQL
     *
     * @param cadena $servidor      Nombre o direcci�n IP del servidor de bases de datos MySQL
     * @param cadena $usuario       Nombre de usuario para la conexi�n al servidor de bases de datos MySQL
     * @param cadena $contrasena    Contrase�a del usuario para la conexi�n al servidor de bases de datos MySQL
     * @param cadena $nombre        Nombre de la base datos para la conexi�n al servidor de bases de datos MySQL
     * @return                      recurso
     *
     */
    public function conectar() {
        $this->conexion = new mysqli($this->servidor, $this->usuario, $this->contrasena, $this->baseDatos);
        if ($this->conexion->connect_errno) {
            echo 'Fallo al conectar a MySQL: (' . $this->conexion->connect_errno . ') ' . $this->conexion->connect_error;
        }
    }





    /**
     *
     * Finalizar una conexi�n con el servidor de bases de datos MySQL
     *
     * @param recurso $conexion     Gestor de la conexi�n a la base de datos MySQL
     * @return                      l�gico
     *
     */
    public function desconectar($conexion = "") {

        if (empty($conexion)) {
            $cierre = $this->conexion->close();
        } else {
            $cierre = mysqli_close($conexion);
        }
    }




    /**
     *
     * Ejecutar una consulta en el servidor de bases de datos MySQL
     *
     * @param cadena $consulta      Instrucci�n SQL a ejecutar
     * @return                      recurso
     *
     */
    public function ejecutar($consulta) {
        global $modulo, $sesion_usuarioSesion;

        $this->consultas++;
        $this->filasDevueltas = NULL;
        $this->filasAfectadas = NULL;
        $horaInicio = microtime(true);
        $this->resultado = $this->conexion->query($consulta);
        $horaFinalizacion = microtime(true);
        $this->tiempo += round($horaFinalizacion - $horaInicio, 4);
        
        if ((!empty($this->conexion->error)) || $this->depurar) {
            openlog('FOLCS', LOG_PID, LOG_LOCAL0);
            $log = syslog(LOG_DEBUG, $this->prefijoDepuracion.' '.$consulta);

            if (!empty($this->conexion->error)) {
                $log = syslog(LOG_DEBUG, $this->conexion->error);
            }

            $this->depurar = false;
        }        

        
        if (preg_match("/^(SELECT|SHOW)/", $consulta) && !$this->conexion->errno) {
            $this->filasDevueltas = $this->resultado->num_rows;
        } else {
            $this->filasAfectadas = $this->conexion->affected_rows;

            //Funciones para guardar registro de actividades en la bitacora
            if ($this->guardarBitacora) {
                $tipo = '';


                if (isset($sesion_usuarioSesion) && !empty($sesion_usuarioSesion->usuario)) {
                    $username = $sesion_usuarioSesion->usuario;
                } else {
                    $username = 'sin sesion';
                }
                if (preg_match("/INSERT/", $consulta)) {
                    $tipo = 'INSERT';
                    $this->ultimoId = $this->conexion->insert_id;
                } else if (preg_match("/DELETE/", $consulta)) {
                    $tipo = 'DELETE';
                } else if (preg_match("/UPDATE/", $consulta)) {
                    $tipo = 'UPDATE';
                }

                $sentencia = "INSERT INTO folcs_bitacora (usuario, ip, tipo, consulta, fecha, modulo) VALUES ('$username', '" . Recursos::getRealIP() . "', '$tipo', '" . addslashes($consulta) . "', '" . date('Y-m-d H:i:s') . "', '$modulo->nombre')";
                $this->conexion->query($sentencia);
            }
            //se pone nuevamente en true, poque pudo haber sido puesto en false en algun metodo de alguna clase
            $this->guardarBitacora = true;
            $this->prefijoDepuracion = '';
        }

        return $this->resultado;
    }



    public static function escribirTxt($texto){
        $fecha = date("d/m/y H:i:s");
        $fp = fopen("errores.txt","w");
        fwrite($fp, "Fecha: $fecha -> \n Variable: $texto  " .PHP_EOL);
        fclose($fp);
    }
    

    /**
     *
     * Convertir el recurso resultante de una consulta en un objeto
     *
     * @param recurso $resultado    Recurso resultante de una consulta
     * @return                      objeto
     *
     */
    public function filaEnObjeto($resultado = NULL) {
	if(empty($resultado)){
	  $fila = $this->resultado->fetch_object();
	} else {
	  $fila = mysqli_fetch_object($resultado);
	}
        
        return $fila;
    }

    /**
     *
     * Convertir el recurso resultante de una consulta en un arreglo
     *
     * @param recurso $resultado    Recurso resultante de una consulta
     * @return                      arreglo
     *
     */
    public function filaEnArreglo($resultado = NULL) {
	if(empty($resultado)){
	  $fila = $this->resultado->fetch_array();
	} else {
	  $fila = mysqli_fetch_array($resultado);
	}        
        return $fila;
    }

    /**
     *
     * Convertir el recurso resultante de una consulta en un arreglo ASOCIATIVO
     *
     * @param recurso $resultado    Recurso resultante de una consulta
     * @return                      arreglo
     *
     */
    public function filaEnArregloAsoc($resultado = NULL) {
	if(empty($resultado)){
	  $fila = $this->resultado->fetch_assoc();
	} else {
	  $fila = mysqli_fetch_assoc($resultado);
	}        
        return $fila;
    }





   /**
     *
     * Seleccionar datos de una o varias tablas del servidor de bases de datos MySQL
     *
     * @return recurso
     *
     */
    public function seleccionar($tablas, $columnas, $condicion = "", $agrupamiento = "", $ordenamiento = "", $filaInicial = NULL, $numeroFilas = NULL) {
        $listaColumnas = array();
        $listaTablas = array();
        $limite = "";

        foreach ($columnas as $alias => $columna) {

            if (preg_match("/(^[a-zA-z]+[a-zA-Z0-9]*)/", $alias)) {
                $alias = " AS $alias";
            } else {
                $alias = "";
            }

            $listaColumnas[] = $columna . $alias;
        }

        $columnas = implode(', ', $listaColumnas);

        foreach ($tablas as $alias => $tabla) {

            if (preg_match("/(^[a-zA-z]+[a-zA-Z0-9]*)/", $alias)) {
                $alias = ' AS '.$alias;
            } else {
                $alias = '';
            }

            $tabla = $this->prefijo . $tabla;
            $listaTablas[] = $tabla . $alias;
        }

        if (!empty($condicion)) {
            $condicion = ' WHERE '. $condicion;
        }

        if (!empty($agrupamiento)) {
            $agrupamiento = ' GROUP BY '.$agrupamiento;
        }

        if (!empty($ordenamiento)) {
            $ordenamiento = ' ORDER BY '.$ordenamiento;
        }

        if (is_int($numeroFilas) && $numeroFilas > 0) {
            $limite = ' LIMIT ';

            if (is_int($filaInicial) && $filaInicial >= 0) {
                $limite .= "$filaInicial, ";
            }

            $limite .= $numeroFilas;
        }

        $tablas = implode(', ', $listaTablas);
        $sentencia = 'SELECT '.$columnas.' FROM '.$tablas. $condicion . $agrupamiento . $ordenamiento . $limite;

        $this->sentenciaSql = $sentencia;

        return $this->ejecutar($sentencia);
    }

    /*     * * Insertar datos en la tabla ** */

    public function insertar($tabla, $datos) {
        $tabla = $this->prefijo . $tabla;

        if (is_array($datos) && count($datos) > 0) {

            foreach ($datos as $campo => $valor) {

                if ($valor != '') {
                    $campos[] = $campo;

                    if (Variable::contieneUTF8($valor)) {
                        $valor = Variable::codificarCadena($valor);
                    }

                    $valores[] = "'$valor'";
                }
            }

            $campos = implode(",", $campos);
            $valores = implode(",", $valores);
            $sentencia = "INSERT INTO $tabla ($campos) VALUES ($valores)";
        }

        $resultado = $this->ejecutar($sentencia);


        return $resultado;
    }

    /*     * * Reemplazar datos existentes en la tabla o insertarlos si no existen ** */

    public function reemplazar($tabla, $datos) {

        $tabla = $this->prefijo . $tabla;

        if (is_array($datos) && count($datos) > 0) {

            foreach ($datos as $campo => $valor) {
                $campos[] = $campo;

                if (Variable::contieneUTF8($valor)) {
                    $valor = Variable::codificarCadena($valor);
                }

                $valores[] = "'$valor'";
            }

            $campos = implode(", ", $campos);
            $valores = implode(", ", $valores);
            $sentencia = "REPLACE INTO $tabla ($campos) VALUES ($valores)";
        }

        $resultado = $this->ejecutar($sentencia);

        return $resultado;
    }

    /*     * * Modificar datos existentes en la tabla de acuerdo con una condici�n ** */

    public function modificar($tabla, $datos, $condicion) {
        $tabla = $this->prefijo . $tabla;

        if (is_array($datos) && count($datos) > 0) {
            $campos = array();

            foreach ($datos as $campo => $valor) {

                if ($valor != "") {

                    if (Variable::contieneUTF8($valor)) {
                        $valor = Variable::codificarCadena($valor);
                    }

                    $valores[] = "$campo='$valor'";
                    $campos["$campo"] = "'$valor'";
                } else {
                    $valores[] = "$campo=NULL";
                    $campos["$campo"] = "NULL";
                }
            }

            $valores = implode(", ", $valores);
            $sentencia = "UPDATE $tabla SET $valores WHERE $condicion";
        }

        $resultado = $this->ejecutar($sentencia);

        return $resultado;
    }

    /*     * * Eliminar datos de una tabla que coincidan con una condici�n  ** */

    public function eliminar($tabla, $condicion) {
        $tabla = $this->prefijo . $tabla;
        $sentencia = "DELETE FROM $tabla WHERE $condicion";

        $resultado = $this->ejecutar($sentencia);

        return $resultado;
    }




    /*** Verificar si un registro con un valor espec�fico existe en una tabla ***/
    /**
     *
     * @param type $tabla
     * @param type $columna
     * @param type $valor
     * @param type $condicionExtra
     * @return type boolean
     */
    public function existeItem($tabla, $columna, $valor, $condicionExtra = "") {
        $tablas = array($tabla);
        $columnas = array($columna);
        $condicion = "$columna = '$valor'";

        if (!empty($condicionExtra)) {
            $condicion .= " AND $condicionExtra";
        }

        $this->seleccionar($tablas, $columnas, $condicion);

        if ($this->filasDevueltas) {
            return TRUE;
        } else {
            return FALSE;
        }
    }

    /*     * * Obtener el valor de un campo en una tabla cuyo registro (�nico) coincida con una condici�n dada ** */

    public function obtenerValor($tabla, $columna, $condicion) {
        $tablas = array($tabla);
        $columnas = array($columna);
        //$this->depurar = true;
        $consulta = $this->seleccionar($tablas, $columnas, $condicion);

        if ($this->filasDevueltas == 1) {
            $datos = $this->filaEnObjeto($consulta);
            $valor = $datos->$columna;
            return $valor;
        } else {
            return FALSE;
        }
    }




    /*** Realizar b�squeda y devolver filas coincidentes ???***/
    public function evaluarBusqueda($vistaBuscador, $vistaMenu) {
        global $componente, $url_buscar, $url_expresion, $sesion_expresion, $sesion_origenExpresion;

        $tabla          = $this->prefijo.$vistaBuscador;
        $camposBuscador = $this->obtenerColumnas($vistaBuscador);
        $camposMenu     = $this->obtenerColumnas($vistaMenu);
        $campoClave     = $camposMenu[0];
        $condicionFinal = "$campoClave IS NOT NULL";

        /*** Verificar si la solicitud proviene del formulario de b�squeda ***/
        if (isset($url_buscar)) {
            if (!empty($url_expresion)) {
                Sesion::registrar("expresion", $url_expresion);
                Sesion::registrar("origenExpresion", $componente->id);
            } else {
                Sesion::borrar("expresion");
                unset($sesion_expresion);
                Sesion::borrar("origenExpresion");
                unset($sesion_origenExpresion);
            }
        } else {
            $condicion = "";
        }

        /*** Verificar si se est� en medio de de una b�usqueda ***/
        if (!empty($sesion_expresion) && ($sesion_origenExpresion == $componente->id)) {
            $expresion    = Texto::expresionRegular($sesion_expresion);
            $campoInicial = true;
            $listaCampos  = array();

            foreach ($camposBuscador as $campo) {
                if (!$campoInicial) {
                    $listaCampos[] = "$tabla.$campo REGEXP '$expresion'";
                }

                $campoInicial = false;
            }

            $condicion = "(".implode(" OR ", $listaCampos).")";
            $tablas    = array($vistaBuscador);
            $columnas  = array($camposBuscador[0]);
            $consulta  = $this->seleccionar($tablas, $columnas, $condicion);

            if ($this->filasDevueltas) {
                $lista = array();

                while ($datos = $this->filaEnObjeto($consulta)) {
                    $lista[] = $datos->id;
                }

                $condicionFinal = "$campoClave IN (".implode(",",$lista).")";

            } else {
                $condicionFinal = "$campoClave IN (NULL)";
            }

        } else {
            Sesion::borrar("expresion");
            unset($sesion_expresion);
            Sesion::borrar("origenExpresion");
            unset($sesion_origenExpresion);
        }

        return $condicionFinal;
    }




    /*** Devolver lista de elementos que coincidan con la b�squeda parcial del usuario para autocompletar ***/
    public function datosAutoCompletar($tabla, $patron) {
        $columnas = $this->obtenerColumnas($tabla);
        $primera  = true;
        $patron   = Texto::expresionRegular($patron, false);

        foreach ($columnas as $columna) {

            if ($primera) {
                $primera = false;
                continue;
            }

            $consulta = $this->seleccionar(array($tabla), array($columna), "CAST($columna AS CHAR) REGEXP '$patron'");

            while ($datos = $this->filaEnArreglo($consulta)) {
                $lista[] = $datos[0];
            }

        }
        natsort($lista);
        $lista = implode("\n", array_unique($lista));
        return $lista;
    }


}
?>